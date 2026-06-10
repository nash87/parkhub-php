<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Retention\RetentionClass;
use App\Services\Retention\RetentionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin endpoints for GDPR data-retention policy management.
 *
 * All routes are guarded by the `module:retention` + `admin` middleware stack.
 * Response envelope: {success, data} / {success, error, message}.
 */
final class RetentionController extends Controller
{
    public function __construct(private readonly RetentionEngine $engine) {}

    /**
     * GET /api/v1/admin/retention/policies
     *
     * Returns all seven retention classes with their current effective TTL,
     * default TTL, legal-hold flag and statutory minimum.
     */
    public function policies(): JsonResponse
    {
        $policies = [];

        foreach (RetentionClass::cases() as $class) {
            $policies[] = $this->policyPayload($class);
        }

        return response()->json(['success' => true, 'data' => ['policies' => $policies]]);
    }

    /**
     * PUT /api/v1/admin/retention/policies/{class}
     *
     * Override the TTL for a specific retention class.
     * Returns 422 for unknown classes or legal-hold violations.
     */
    public function updatePolicy(Request $request, string $class): JsonResponse
    {
        $classEnum = RetentionClass::tryFrom($class);

        if ($classEnum === null) {
            return response()->json([
                'success' => false,
                'error' => 'UNKNOWN_CLASS',
                'message' => "Unknown retention class: {$class}.",
            ], 422);
        }

        $validated = $request->validate(['ttl_days' => 'required|integer|min:1']);

        try {
            $this->engine->setTtlDays($classEnum, $validated['ttl_days']);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => 'LEGAL_HOLD_VIOLATION',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->policyPayload($classEnum),
        ]);
    }

    /**
     * POST /api/v1/admin/retention/run
     *
     * Execute a retention purge run.
     * Body: {"dry_run": true|false} (default false).
     */
    public function run(Request $request): JsonResponse
    {
        $dryRun = (bool) $request->input('dry_run', false);

        $results = $this->engine->purge($dryRun);

        return response()->json([
            'success' => true,
            'data' => [
                'results' => $results,
                'ran_at' => now()->toIso8601String(),
                'dry_run' => $dryRun,
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/retention/evidence
     *
     * Returns deletion-evidence log entries (RetentionPurgeRun AuditLog rows).
     */
    public function evidence(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 100), 1000);

        return response()->json([
            'success' => true,
            'data' => ['evidence' => $this->engine->getEvidence($limit)],
        ]);
    }

    /** @return array<string, mixed> */
    private function policyPayload(RetentionClass $class): array
    {
        return [
            'class' => $class->value,
            'ttl_days' => $this->engine->getTtlDays($class),
            'default_ttl_days' => $class->defaultTtlDays(),
            'is_legal_hold' => $class->isLegalHold(),
            'statutory_min_days' => $class->statutoryMinDays(),
        ];
    }
}
