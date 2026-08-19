<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Compliance\ComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function __construct(private readonly ComplianceService $service) {}

    /**
     * GET /admin/compliance/report — GDPR/DSGVO compliance status with 10 checks.
     */
    public function report(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->report(),
        ]);
    }

    /**
     * GET /admin/compliance/data-map — Art. 30 data processing inventory.
     */
    public function dataMap(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->dataMap(),
        ]);
    }

    /**
     * GET /admin/compliance/audit-export — audit trail CSV or JSON.
     */
    public function auditExport(Request $request): JsonResponse
    {
        $format = $request->query('format', 'json');
        $limit = min((int) $request->query('limit', 1000), 5000);

        $logs = $this->service->auditLogs($limit);

        if ($logs === null) {
            // The audit trail is the artifact an auditor asks for. If it
            // cannot be produced, reporting `success: true` with an empty
            // list is a silent false negative in exactly the wrong place —
            // it is indistinguishable from "this install has no activity".
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'AUDIT_TRAIL_UNAVAILABLE',
                    'message' => 'The audit log table is not present on this installation, so no audit trail can be exported.',
                ],
                'meta' => null,
            ], 503);
        }

        if ($format === 'csv') {
            return response()->json([
                'success' => true,
                'data' => [
                    'format' => 'csv',
                    'content' => $this->service->auditLogsCsv($logs),
                    'count' => count($logs),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'format' => 'json',
                'logs' => $logs,
                'count' => count($logs),
                'exported_at' => now()->toISOString(),
            ],
        ]);
    }
}
