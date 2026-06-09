<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\Setting;
use App\Services\ModuleRegistry;
use App\Services\Recommendations\ExactCoverAllocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecommendationController extends Controller
{
    /**
     * GET /api/v1/bookings/recommendations
     *
     * Suggest optimal parking slots using weighted scoring:
     * - Frequency 40%: past usage of the specific slot
     * - Availability 30%: is the slot available right now
     * - Price 20%: lot hourly rate (lower is better)
     * - Distance 10%: lower slot number = closer to entrance
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $lotId = $request->query('lot_id');
        $engine = $this->recommendationEngineConfig();
        $weights = $engine['weights'];
        $recommendationId = (string) Str::uuid();

        // 1. Get user's booking history (completed/active/confirmed only)
        $bookings = Booking::where('user_id', $user->id)
            ->whereIn('status', ['active', 'completed', 'confirmed'])
            ->get();

        // 2. Count slot and lot usage frequency
        $slotFrequency = $bookings->groupBy('slot_id')->map->count();
        $lotFrequency = $bookings->groupBy('lot_id')->map->count();

        // 3. Get lots (optionally filtered)
        $lotsQuery = ParkingLot::with(['slots' => function ($q) {
            $q->where('status', 'available')
                ->whereDoesntHave('activeBooking');
        }]);

        if ($lotId) {
            $lotsQuery->where('id', $lotId);
        }

        $lots = $lotsQuery->get();

        // 4. Compute price stats for normalization
        $prices = $lots->pluck('hourly_rate')->filter()->values();
        $maxPrice = $prices->max() ?: 1;

        // 5. Score each available slot with weighted algorithm
        $candidates = collect();

        foreach ($lots as $lot) {
            $lotRate = $lot->hourly_rate ?: 0;

            foreach ($lot->slots as $slot) {
                $score = 0.0;
                $reasons = [];
                $badges = [];

                // Frequency component (40% weight, max 40 points)
                $freq = $slotFrequency->get($slot->id, 0);
                $lotFreq = $lotFrequency->get($lot->id, 0);
                if ($freq > 0) {
                    $score += min($freq, 10) * ($weights['frequency'] / 10.0);
                    $reasons[] = "Used {$freq} times before";
                    $badges[] = 'your_usual_spot';
                } elseif ($lotFreq > 0) {
                    $score += min($lotFreq, 10) * ($weights['preferred_lot'] / 10.0);
                    $reasons[] = "In your preferred lot (used {$lotFreq} times)";
                    $badges[] = 'preferred_lot';
                }

                // Availability component (30% weight)
                $score += $weights['availability'];
                $badges[] = 'available_now';
                if (empty($reasons)) {
                    $reasons[] = 'Available now';
                }

                // Price component (20% weight — lower price = higher score)
                if ($maxPrice > 0) {
                    $priceScore = (1 - ($lotRate / $maxPrice)) * $weights['price'];
                    $score += $priceScore;
                    if ($priceScore >= ($weights['price'] * 0.75)) {
                        $badges[] = 'best_price';
                        $reasons[] = 'Great price';
                    }
                }

                // Distance component (10% weight — lower slot number = closer)
                $slotNum = (int) $slot->slot_number;
                $distanceScore = $weights['distance'] / max($slotNum, 1);
                $score += $distanceScore;
                if ($distanceScore >= ($weights['distance'] * 0.5)) {
                    $badges[] = 'closest_entrance';
                    $reasons[] = 'Near entrance';
                }

                // Bonus for accessible slots
                if ($slot->is_accessible ?? false) {
                    $score += $weights['accessibility_bonus'];
                    $badges[] = 'accessible';
                    $reasons[] = 'Accessible';
                }

                // Bonus for slot features
                $features = $slot->features ?? [];
                if (! empty($features)) {
                    $score += $weights['feature_bonus'];
                    $featureNames = implode(', ', array_map('ucfirst', $features));
                    $reasons[] = "Features: {$featureNames}";
                }

                $candidates->push([
                    'recommendation_id' => $recommendationId,
                    'slot_id' => $slot->id,
                    'slot_number' => (int) $slot->slot_number,
                    'lot_id' => $lot->id,
                    'lot_name' => $lot->name,
                    'floor_name' => 'Ground',
                    'score' => round($score, 2),
                    'reasons' => $reasons,
                    'reason_badges' => array_values(array_unique($badges)),
                ]);
            }
        }

        // Sort by local score first; fop_pipeline_v1 receives the full set and
        // max_results is applied only after external ranking or fallback.
        $rankedCandidates = $candidates->sortByDesc('score')->values();
        $adapter = $this->weightedV1AdapterStatus($engine);
        if ($engine['algorithm'] === 'fop_pipeline_v1') {
            [$rankedCandidates, $adapter] = $this->tryFopPipelineRecommendations(
                $engine,
                $recommendationId,
                $rankedCandidates->all()
            );
        }
        $top = $rankedCandidates->take($engine['max_results'])->values();
        $this->auditRecommendationServed($request, $recommendationId, $engine, $adapter, $top->all());

        return response()->json([
            'success' => true,
            'data' => $top,
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/recommendations/stats — admin acceptance rate.
     */
    public function stats(): JsonResponse
    {
        $engine = $this->recommendationEngineConfig();
        $servedEvents = AuditLog::query()
            ->where('action', 'recommendation_served')
            ->orWhere('event_type', 'RecommendationServed')
            ->get();
        $servedStats = $this->servedRecommendationStats($servedEvents);

        return response()->json([
            'success' => true,
            'data' => [
                'total_recommendations' => $servedStats['total_recommendation_batches'],
                'total_recommendations_served' => $servedStats['total_recommendations_served'],
                'accepted_recommendations' => null,
                'acceptance_rate' => null,
                'acceptance_metric_source' => 'not_tracked',
                'unique_users' => $servedStats['unique_users'],
                'avg_score' => $servedStats['avg_score'],
                'metrics_source' => 'audit_log.recommendation_served',
                'algorithm' => $engine['algorithm'],
                'algorithm_weights' => $engine['weights'],
                'allocation' => $engine['allocation'],
                'algorithm_adapter' => $this->weightedV1AdapterStatus($engine),
                'legal_boundary' => [
                    'legal_review_required' => true,
                    'attorney_review_status' => 'required_before_customer_wording',
                    'execution_allowed' => false,
                    'disclaimer' => 'fop legal output is reference-only drafting support; attorney review, citation verification, client authorization, and final legal judgment remain required before customer-facing profiling or legal wording ships.',
                ],
                'top_recommended_lots' => $servedStats['top_recommended_lots'],
            ],
            'error' => null,
        ]);
    }

    /**
     * POST /api/v1/recommendations/allocation/exact-cover
     *
     * Admin-only exact-cover utility for batch/recurring operational
     * scheduling. The quick-booking endpoint keeps weighted_v1 as its default.
     */
    public function exactCoverAllocation(Request $request, ExactCoverAllocator $allocator): JsonResponse
    {
        $validated = $request->validate([
            'required_constraints' => ['present', 'array', 'max:256'],
            'required_constraints.*' => ['string', 'max:128'],
            'options' => ['present', 'array', 'max:256'],
            'options.*.id' => ['required', 'string', 'max:128'],
            'options.*.covers' => ['present', 'array', 'min:1', 'max:256'],
            'options.*.covers.*' => ['nullable', 'string', 'max:128'],
            'options.*.weight' => ['sometimes', 'integer', 'min:-1000000', 'max:1000000'],
            'limits' => ['sometimes', 'array'],
            'limits.max_options' => ['sometimes', 'integer', 'min:1', 'max:256'],
            'limits.max_search_nodes' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ]);
        $engine = $this->recommendationEngineConfig();
        $limits = (array) ($validated['limits'] ?? []);
        $options = array_values((array) $validated['options']);
        $duplicateOptionIds = $this->duplicateExactCoverOptionIds($options);
        if ($duplicateOptionIds !== []) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'DUPLICATE_EXACT_COVER_OPTION_ID',
                    'message' => 'Exact-cover option IDs must be unique: '.implode(', ', $duplicateOptionIds),
                ],
            ], 422);
        }
        $emptyCoverOptionIds = $this->emptyCoverExactCoverOptionIds($options);
        if ($emptyCoverOptionIds !== []) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'EXACT_COVER_OPTION_COVERS_REQUIRED',
                    'message' => 'Exact-cover options must cover at least one constraint: '.implode(', ', $emptyCoverOptionIds),
                ],
            ], 422);
        }
        $effectiveLimits = [
            'exact_cover_max_options' => $this->boundedInt(
                $limits['max_options'] ?? $engine['allocation']['exact_cover_max_options'],
                1,
                $engine['allocation']['exact_cover_max_options']
            ),
            'exact_cover_max_search_nodes' => $this->boundedInt(
                $limits['max_search_nodes'] ?? $engine['allocation']['exact_cover_max_search_nodes'],
                1,
                $engine['allocation']['exact_cover_max_search_nodes']
            ),
        ];

        $result = $allocator->solve(
            array_values((array) $validated['required_constraints']),
            $options,
            $effectiveLimits['exact_cover_max_options'],
            $effectiveLimits['exact_cover_max_search_nodes']
        );
        $allocationTraceId = (string) Str::uuid();
        $this->auditExactCoverAllocation($request, $allocationTraceId, $effectiveLimits, $validated, $result);

        return response()->json([
            'success' => true,
            'data' => [
                'allocation_trace_id' => $allocationTraceId,
                'result' => $result,
                'legal_boundary' => [
                    'legal_review_required' => true,
                    'attorney_review_status' => 'required_before_customer_wording',
                    'execution_allowed' => false,
                    'disclaimer' => 'exact_cover_v1 is operational scheduling support; attorney review, citation verification, client authorization, and final legal judgment remain required before customer-facing legal or profiling claims ship.',
                ],
            ],
            'error' => null,
        ]);
    }

    /**
     * Versioned default engine config. This preserves the legacy weighted_v1
     * scores while giving ParkHub a stable seam for fop-pipeline adoption.
     *
     * @return array{
     *     algorithm: string,
     *     weights: array<string, float>,
     *     max_results: int,
     *     explain: bool,
     *     profile_safe_mode: bool,
     *     pipeline: array{endpoint: ?string, pipeline_name: string, timeout_ms: int, fallback_enabled: bool},
     *     allocation: array{strategy: string, exact_cover_max_options: int, exact_cover_max_search_nodes: int}
     * }
     */
    private function recommendationEngineConfig(): array
    {
        $weights = (array) config('recommendations.weights', []);
        $algorithm = (string) $this->moduleConfigValue(
            'algorithm',
            config('recommendations.algorithm', 'weighted_v1'),
        );
        if (! in_array($algorithm, ['weighted_v1', 'fop_pipeline_v1'], true)) {
            $algorithm = 'weighted_v1';
        }
        $pipeline = (array) config('recommendations.pipeline', []);
        $pipelineEndpoint = $this->validatedPipelineEndpoint(
            (string) $this->moduleConfigValue('pipeline_endpoint', $pipeline['endpoint'] ?? '')
        );
        $pipelineName = (string) $this->moduleConfigValue(
            'pipeline_name',
            $pipeline['pipeline_name'] ?? 'parkhub-recommendations'
        );
        $pipelineName = trim($pipelineName) !== '' ? trim($pipelineName) : 'parkhub-recommendations';
        $allocation = (array) config('recommendations.allocation', []);
        $allocationStrategy = (string) $this->moduleConfigValue(
            'allocation_strategy',
            $allocation['strategy'] ?? 'weighted_v1'
        );
        if (! in_array($allocationStrategy, ['weighted_v1', 'exact_cover_v1'], true)) {
            $allocationStrategy = 'weighted_v1';
        }

        return [
            'algorithm' => $algorithm,
            'weights' => [
                'frequency' => $this->boundedFloat(
                    $this->moduleConfigValue('weight_frequency', $weights['frequency'] ?? 40.0),
                    0.0,
                    100.0
                ),
                'preferred_lot' => $this->boundedFloat(
                    $this->moduleConfigValue('weight_preferred_lot', $weights['preferred_lot'] ?? 20.0),
                    0.0,
                    100.0
                ),
                'availability' => $this->boundedFloat(
                    $this->moduleConfigValue('weight_availability', $weights['availability'] ?? 30.0),
                    0.0,
                    100.0
                ),
                'price' => $this->boundedFloat(
                    $this->moduleConfigValue('weight_price', $weights['price'] ?? 20.0),
                    0.0,
                    100.0
                ),
                'distance' => $this->boundedFloat(
                    $this->moduleConfigValue('weight_distance', $weights['distance'] ?? 10.0),
                    0.0,
                    100.0
                ),
                'accessibility_bonus' => $this->boundedFloat(
                    $this->moduleConfigValue(
                        'weight_accessibility_bonus',
                        $weights['accessibility_bonus'] ?? 0.0
                    ),
                    0.0,
                    25.0
                ),
                'feature_bonus' => $this->boundedFloat(
                    $this->moduleConfigValue('weight_feature_bonus', $weights['feature_bonus'] ?? 2.0),
                    0.0,
                    25.0
                ),
            ],
            'max_results' => max(
                1,
                min(25, (int) $this->moduleConfigValue('max_results', config('recommendations.max_results', 5)))
            ),
            'explain' => true,
            'profile_safe_mode' => true,
            'pipeline' => [
                'endpoint' => $pipelineEndpoint,
                'pipeline_name' => $pipelineName,
                'timeout_ms' => max(
                    100,
                    min(5000, (int) $this->moduleConfigValue('pipeline_timeout_ms', $pipeline['timeout_ms'] ?? 750))
                ),
                'fallback_enabled' => true,
            ],
            'allocation' => [
                'strategy' => $allocationStrategy,
                'exact_cover_max_options' => $this->boundedInt(
                    $this->moduleConfigValue(
                        'exact_cover_max_options',
                        $allocation['exact_cover_max_options'] ?? 256
                    ),
                    1,
                    256
                ),
                'exact_cover_max_search_nodes' => $this->boundedInt(
                    $this->moduleConfigValue(
                        'exact_cover_max_search_nodes',
                        $allocation['exact_cover_max_search_nodes'] ?? 10000
                    ),
                    1,
                    10000
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $engine
     * @return array{
     *     requested_algorithm: string,
     *     effective_algorithm: string,
     *     attempted: bool,
     *     status: string,
     *     pipeline_name: ?string,
     *     endpoint_configured: bool,
     *     fallback_enabled: bool,
     *     error: ?string
     * }
     */
    private function weightedV1AdapterStatus(array $engine): array
    {
        return [
            'requested_algorithm' => (string) $engine['algorithm'],
            'effective_algorithm' => 'weighted_v1',
            'attempted' => false,
            'status' => 'weighted_v1',
            'pipeline_name' => null,
            'endpoint_configured' => ! empty($engine['pipeline']['endpoint']),
            'fallback_enabled' => true,
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $engine
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    private function tryFopPipelineRecommendations(array $engine, string $recommendationId, array $candidates): array
    {
        $endpoint = $engine['pipeline']['endpoint'] ?? null;
        if (empty($endpoint)) {
            return [
                collect($candidates),
                $this->fallbackAdapterStatus(
                    $engine,
                    false,
                    'fallback_not_configured',
                    'fop_pipeline_v1 endpoint is not configured'
                ),
            ];
        }

        try {
            $url = rtrim((string) $endpoint, '/').'/pipeline/'
                .trim((string) $engine['pipeline']['pipeline_name'], '/').'/run';
            $timeoutSeconds = ((int) $engine['pipeline']['timeout_ms']) / 1000;
            $response = Http::timeout($timeoutSeconds)
                ->connectTimeout(min($timeoutSeconds, 1.0))
                ->post($url, [
                    'schema_version' => 'parkhub.recommendation.pipeline.v1',
                    'recommendation_id' => $recommendationId,
                    'algorithm' => 'fop_pipeline_v1',
                    'fallback_algorithm' => 'weighted_v1',
                    'weights' => $engine['weights'],
                    'max_results' => $engine['max_results'],
                    'explain' => $engine['explain'],
                    'profile_safe_mode' => $engine['profile_safe_mode'],
                    'candidates' => $candidates,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('fop-pipeline returned HTTP '.$response->status());
            }

            $ranked = (array) data_get($response->json(), 'data.ranked', []);
            $pipelineCandidates = $this->applyFopPipelineRankedResponse($candidates, $ranked);
            if ($pipelineCandidates === []) {
                throw new \RuntimeException('fop-pipeline response did not rank any known slots');
            }

            return [
                collect($pipelineCandidates),
                [
                    'requested_algorithm' => 'fop_pipeline_v1',
                    'effective_algorithm' => 'fop_pipeline_v1',
                    'attempted' => true,
                    'status' => 'succeeded',
                    'pipeline_name' => (string) $engine['pipeline']['pipeline_name'],
                    'endpoint_configured' => true,
                    'fallback_enabled' => true,
                    'error' => null,
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('fop_pipeline_v1 recommendation attempt failed; falling back to weighted_v1', [
                'recommendation_id' => $recommendationId,
                'error' => $e->getMessage(),
            ]);

            return [
                collect($candidates),
                $this->fallbackAdapterStatus($engine, true, 'fallback_error', $e->getMessage()),
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  array<int, array<string, mixed>>  $ranked
     * @return array<int, array<string, mixed>>
     */
    private function applyFopPipelineRankedResponse(array $candidates, array $ranked): array
    {
        $bySlotId = collect($candidates)->keyBy(fn (array $candidate) => (string) $candidate['slot_id']);
        $out = [];
        $seen = [];
        foreach ($ranked as $item) {
            $slotId = (string) ($item['slot_id'] ?? $item['id'] ?? '');
            if ($slotId === '' || ! $bySlotId->has($slotId)) {
                throw new \RuntimeException("fop-pipeline ranked unknown slot_id '{$slotId}'");
            }
            if (isset($seen[$slotId])) {
                continue;
            }
            $candidate = $bySlotId->get($slotId);
            if (array_key_exists('score', $item)) {
                $candidate['score'] = round((float) $item['score'], 2);
            }
            if (isset($item['reasons']) && is_array($item['reasons'])) {
                $candidate['reasons'] = array_values($item['reasons']);
            }
            if (isset($item['reason_badges']) && is_array($item['reason_badges'])) {
                $candidate['reason_badges'] = array_values($item['reason_badges']);
            }
            $out[] = $candidate;
            $seen[$slotId] = true;
        }

        foreach ($candidates as $candidate) {
            $slotId = (string) $candidate['slot_id'];
            if (! isset($seen[$slotId])) {
                $out[] = $candidate;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $engine
     * @return array<string, mixed>
     */
    private function fallbackAdapterStatus(array $engine, bool $attempted, string $status, string $error): array
    {
        return [
            'requested_algorithm' => (string) $engine['algorithm'],
            'effective_algorithm' => 'weighted_v1',
            'attempted' => $attempted,
            'status' => $status,
            'pipeline_name' => (string) $engine['pipeline']['pipeline_name'],
            'endpoint_configured' => ! empty($engine['pipeline']['endpoint']),
            'fallback_enabled' => true,
            'error' => $error,
        ];
    }

    private function validatedPipelineEndpoint(string $endpoint): ?string
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return null;
        }

        $parts = parse_url($endpoint);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host)) {
            Log::warning('recommendation pipeline_endpoint rejected as invalid URL', ['endpoint' => $endpoint]);

            return null;
        }

        $allowed = in_array($host, ['localhost', '127.0.0.1', '::1', 'fop-pipeline'], true)
            || str_ends_with($host, '.svc')
            || str_ends_with($host, '.svc.cluster.local')
            || str_ends_with($host, '.test');
        if (! $allowed) {
            Log::warning('recommendation pipeline_endpoint rejected by local/cluster allowlist', ['endpoint' => $endpoint]);

            return null;
        }

        return $endpoint;
    }

    /**
     * @param  Collection<int, AuditLog>  $servedEvents
     * @return array{
     *     total_recommendation_batches: int,
     *     total_recommendations_served: int,
     *     unique_users: int,
     *     avg_score: ?float,
     *     top_recommended_lots: list<array{lot_id: string, lot_name: string|null, count: int}>
     * }
     */
    private function servedRecommendationStats(Collection $servedEvents): array
    {
        $scores = [];
        $lotCounts = [];
        $userIds = [];
        $servedCandidates = 0;

        foreach ($servedEvents as $event) {
            if ($event->user_id !== null) {
                $userIds[(string) $event->user_id] = true;
            }

            $candidates = (array) data_get($event->details, 'candidates', []);
            $servedCandidates += count($candidates);
            foreach ($candidates as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }

                if (isset($candidate['score']) && is_numeric($candidate['score'])) {
                    $scores[] = (float) $candidate['score'];
                }

                $lotId = (string) ($candidate['lot_id'] ?? '');
                if ($lotId !== '') {
                    $lotCounts[$lotId] = ($lotCounts[$lotId] ?? 0) + 1;
                }
            }
        }

        arsort($lotCounts);
        $topLotIds = array_slice(array_keys($lotCounts), 0, 5);
        $lotNames = ParkingLot::query()
            ->whereIn('id', $topLotIds)
            ->pluck('name', 'id')
            ->all();
        $topLots = collect($topLotIds)
            ->map(fn (string $lotId): array => [
                'lot_id' => $lotId,
                'lot_name' => isset($lotNames[$lotId]) ? (string) $lotNames[$lotId] : null,
                'count' => (int) $lotCounts[$lotId],
            ])
            ->values()
            ->all();

        return [
            'total_recommendation_batches' => $servedEvents->count(),
            'total_recommendations_served' => $servedCandidates,
            'unique_users' => count($userIds),
            'avg_score' => $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
            'top_recommended_lots' => $topLots,
        ];
    }

    /**
     * @param  array{
     *     algorithm: string,
     *     weights: array<string, float>,
     *     max_results: int,
     *     explain: bool,
     *     profile_safe_mode: bool,
     *     pipeline: array<string, mixed>,
     *     allocation: array{strategy: string, exact_cover_max_options: int, exact_cover_max_search_nodes: int}
     * }  $engine
     * @param  array<string, mixed>  $adapter
     * @param  array<int, array<string, mixed>>  $recommendations
     */
    private function auditRecommendationServed(
        Request $request,
        string $recommendationId,
        array $engine,
        array $adapter,
        array $recommendations
    ): void {
        $actor = $request->user();
        $username = $actor === null ? null : ($actor->email ?: $actor->username);
        $candidates = array_map(static fn (array $rec): array => [
            'slot_id' => (string) ($rec['slot_id'] ?? ''),
            'lot_id' => (string) ($rec['lot_id'] ?? ''),
            'score' => (float) ($rec['score'] ?? 0.0),
            'reason_badges' => array_values((array) ($rec['reason_badges'] ?? [])),
            'reasons' => array_values((array) ($rec['reasons'] ?? [])),
        ], $recommendations);

        AuditLog::log([
            'user_id' => $actor?->id,
            'username' => $username,
            'action' => 'recommendation_served',
            'event_type' => 'RecommendationServed',
            'target_type' => 'recommendation',
            'target_id' => $recommendationId,
            'ip_address' => $request->ip(),
            'details' => [
                'recommendation_id' => $recommendationId,
                'algorithm' => $engine['algorithm'],
                'config_hash' => $this->recommendationConfigHash($engine),
                'weights_hash' => $this->recommendationWeightsHash($engine['weights']),
                'adapter' => $adapter,
                'profile_safe_mode' => $engine['profile_safe_mode'],
                'explain' => $engine['explain'],
                'candidate_ids' => array_column($candidates, 'slot_id'),
                'candidates' => $candidates,
                'legal_boundary' => [
                    'legal_review_required' => true,
                    'attorney_review_status' => 'required_before_customer_wording',
                    'execution_allowed' => false,
                ],
            ],
        ]);
    }

    /**
     * @param  array{exact_cover_max_options: int, exact_cover_max_search_nodes: int}  $effectiveLimits
     * @param  array{required_constraints: array<int, string>, options: array<int, array<string, mixed>>, limits?: array<string, int>}  $validated
     * @param  array{strategy: string, status: string, selected_option_ids: array<int, string>, covered_constraints: array<int, string>, search_nodes: int}  $result
     */
    private function auditExactCoverAllocation(
        Request $request,
        string $allocationTraceId,
        array $effectiveLimits,
        array $validated,
        array $result
    ): void {
        $actor = $request->user();
        $selected = array_fill_keys($result['selected_option_ids'], true);
        $candidateIds = array_values(array_filter(array_map(
            static fn (array $option): string => trim((string) ($option['id'] ?? '')),
            $validated['options']
        )));
        $rejectedCandidateIds = array_values(array_filter(
            $candidateIds,
            static fn (string $id): bool => ! isset($selected[$id])
        ));

        AuditLog::log([
            'user_id' => $actor?->id,
            'username' => $actor === null ? null : ($actor->email ?: $actor->username),
            'action' => 'exact_cover_allocation_served',
            'event_type' => 'ExactCoverAllocationServed',
            'target_type' => 'recommendation_allocation',
            'target_id' => $allocationTraceId,
            'ip_address' => $request->ip(),
            'details' => [
                'request_id' => $allocationTraceId,
                'solver_name' => 'exact_cover_v1',
                'solver_version' => 1,
                'config_hash' => $this->exactCoverConfigHash($effectiveLimits),
                'constraint_set_hash' => $this->exactCoverConstraintHash($validated['required_constraints']),
                'candidate_set_hash' => $this->exactCoverCandidateHash($validated['options']),
                'effective_limits' => [
                    'max_options' => $effectiveLimits['exact_cover_max_options'],
                    'max_search_nodes' => $effectiveLimits['exact_cover_max_search_nodes'],
                ],
                'selected_option_ids' => $result['selected_option_ids'],
                'rejected_candidate_ids' => $rejectedCandidateIds,
                'covered_constraints' => $result['covered_constraints'],
                'search_nodes' => $result['search_nodes'],
                'tie_break_inputs' => [
                    'candidate_order' => 'weight_desc_then_option_id_asc',
                    'constraint_order' => 'fewest_candidates_then_constraint_asc',
                    'max_options' => $effectiveLimits['exact_cover_max_options'],
                    'max_search_nodes' => $effectiveLimits['exact_cover_max_search_nodes'],
                ],
                'actor' => [
                    'user_id' => $actor?->id,
                    'api_key_id' => null,
                ],
                'tenant_id' => $actor?->tenant_id,
                'fallback_status' => $result['status'],
                'retention_deletion_class' => 'operational_evidence_personal_data_possible',
                'legal_boundary' => [
                    'legal_review_required' => true,
                    'attorney_review_status' => 'required_before_customer_wording',
                    'execution_allowed' => false,
                ],
            ],
        ]);
    }

    /**
     * @param  array{
     *     algorithm: string,
     *     weights: array<string, float>,
     *     max_results: int,
     *     explain: bool,
     *     profile_safe_mode: bool,
     *     pipeline: array<string, mixed>,
     *     allocation: array{strategy: string, exact_cover_max_options: int, exact_cover_max_search_nodes: int}
     * }  $engine
     */
    private function recommendationConfigHash(array $engine): string
    {
        return hash('sha256', json_encode([
            'algorithm' => $engine['algorithm'],
            'weights' => $engine['weights'],
            'max_results' => $engine['max_results'],
            'explain' => $engine['explain'],
            'profile_safe_mode' => $engine['profile_safe_mode'],
            'pipeline' => $engine['pipeline'],
            'allocation' => $engine['allocation'],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, float>  $weights
     */
    private function recommendationWeightsHash(array $weights): string
    {
        return hash('sha256', json_encode($weights, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array{exact_cover_max_options: int, exact_cover_max_search_nodes: int}  $allocation
     */
    private function exactCoverConfigHash(array $allocation): string
    {
        return hash('sha256', json_encode([
            'strategy' => 'exact_cover_v1',
            'max_options' => $allocation['exact_cover_max_options'],
            'max_search_nodes' => $allocation['exact_cover_max_search_nodes'],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, string>
     */
    private function duplicateExactCoverOptionIds(array $options): array
    {
        $seen = [];
        $duplicates = [];
        foreach ($options as $option) {
            $id = trim((string) ($option['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            if (isset($seen[$id])) {
                $duplicates[$id] = true;

                continue;
            }
            $seen[$id] = true;
        }

        $values = array_keys($duplicates);
        sort($values, SORT_STRING);

        return $values;
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, string>
     */
    private function emptyCoverExactCoverOptionIds(array $options): array
    {
        $ids = [];
        foreach ($options as $option) {
            $id = trim((string) ($option['id'] ?? ''));
            if ($id !== '' && $this->normalizedExactCoverConstraints((array) ($option['covers'] ?? [])) === []) {
                $ids[] = $id;
            }
        }

        sort($ids, SORT_STRING);

        return $ids;
    }

    /**
     * @param  array<int, string>  $constraints
     */
    private function exactCoverConstraintHash(array $constraints): string
    {
        return hash('sha256', json_encode($this->normalizedExactCoverConstraints($constraints), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    private function exactCoverCandidateHash(array $options): string
    {
        $normalized = array_values(array_filter(array_map(function (array $option): ?array {
            $id = trim((string) ($option['id'] ?? ''));
            if ($id === '') {
                return null;
            }

            return [
                'id' => $id,
                'covers' => $this->normalizedExactCoverConstraints((array) ($option['covers'] ?? [])),
                'weight' => (int) ($option['weight'] ?? 0),
            ];
        }, $options)));

        usort($normalized, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<int, mixed>  $constraints
     * @return array<int, string>
     */
    private function normalizedExactCoverConstraints(array $constraints): array
    {
        return ExactCoverAllocator::normalizeConstraints($constraints);
    }

    private function moduleConfigValue(string $key, mixed $default): mixed
    {
        $raw = Setting::get(ModuleRegistry::configSettingKey('recommendations', $key));
        if ($raw === null) {
            return $default;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }

    private function boundedFloat(mixed $value, float $min, float $max): float
    {
        $number = is_numeric($value) ? (float) $value : $min;

        return max($min, min($max, $number));
    }

    private function boundedInt(mixed $value, int $min, int $max): int
    {
        $number = is_numeric($value) ? (int) $value : $min;

        return max($min, min($max, $number));
    }
}
