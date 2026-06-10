<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Fairness\FairnessReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Betriebsrat fairness & §87 BetrVG transparency endpoints (roadmap P0-2).
 *
 * All routes are admin-only and guarded by the `module:fairness` middleware.
 * A future slice will add the `works_council` role as an additional authorised
 * principal without individual-data access (tracked as a separate roadmap item).
 *
 * Privacy contract:
 *  - The fairness report returns ONLY aggregate metrics — never individual data.
 *  - k-anonymity is enforced in FairnessReportService: buckets with fewer than
 *    K_ANON_THRESHOLD users are folded into a catch-all "other" bucket.
 */
final class FairnessController extends Controller
{
    public function __construct(private readonly FairnessReportService $service) {}

    /**
     * GET /api/v1/admin/fairness/report?from=&to=
     *
     * Aggregate-only fairness report over the given ISO-8601 date window.
     *
     * Query parameters:
     *  - from: ISO-8601 datetime (default: 30 days ago)
     *  - to:   ISO-8601 datetime (default: now)
     *
     * Response fields:
     *  - window: {from, to} — effective date window used
     *  - total_allocations: int — total recommendation/exact-cover events
     *  - unique_users_allocated: int — distinct users who received an allocation
     *  - allocation_frequency_buckets: object — per-bucket user counts (k-anonymised)
     *  - denial_reasons: object — unsolved allocation statuses with counts
     *  - booking_to_allocation_ratio: float|null — bookings / allocations ratio
     *  - gini_coefficient: float|null — inequality measure over per-user counts (0=equal)
     *  - k_anonymity_threshold: int — minimum cohort size (buckets below this are folded)
     */
    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->subDays(30)->startOfDay();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            return response()->json([
                'success' => false,
                'error' => 'INVALID_WINDOW',
                'message' => "Parameter 'from' must be before or equal to 'to'.",
            ], 422);
        }

        $data = $this->service->allocationReport($from, $to);

        return response()->json([
            'success' => true,
            'data' => $data,
            'error' => null,
        ]);
    }

    /**
     * GET /api/v1/admin/transparency/data-collection
     *
     * Machine-readable §87 BetrVG disclosure.
     *
     * Returns one entry per RetentionClass with:
     *  - class: string (snake_case identifier, cross-repo stable)
     *  - purpose: string (human-readable German purpose statement)
     *  - default_ttl_days: int
     *  - statutory_min_days: int|null (non-null only for legal-hold classes)
     *  - is_legal_hold: bool
     *  - surfaces: string[] (which booking/check-in/charging interactions generate this data)
     *
     * Also declares:
     *  - no_covert_monitoring: true — no covert monitoring of employees
     *  - no_performance_evaluation: true — data not used for individual performance assessment
     */
    public function dataCollection(): JsonResponse
    {
        $data = $this->service->dataCollectionDisclosure();

        return response()->json([
            'success' => true,
            'data' => $data,
            'error' => null,
        ]);
    }
}
