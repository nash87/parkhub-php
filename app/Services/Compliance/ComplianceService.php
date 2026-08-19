<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns the GDPR/DSGVO compliance report, Art. 30 data-processing
 * inventory and audit-log export assembly extracted from
 * ComplianceController (T-1742, pass 3).
 *
 * Pure extraction — the 10-check list, legal-basis matrix, retention
 * table, processing-activity inventory and CSV/JSON export shape all
 * match the previous inline controller implementation. Controllers
 * remain responsible for auth gating and HTTP shaping.
 */
final class ComplianceService
{
    /**
     * Build the full compliance report payload consumed by the
     * /admin/compliance/report endpoint.
     *
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $checks = $this->runChecks();

        return [
            'generated_at' => now()->toISOString(),
            'overall_status' => $this->computeOverallStatus($checks),
            'checks' => $checks,
            'data_categories' => $this->dataCategories(),
            'legal_basis' => $this->legalBasis(),
            'retention_periods' => $this->retentionPeriods(),
            'sub_processors' => $this->subProcessors(),
            'tom_summary' => $this->tomSummary($checks),
        ];
    }

    /**
     * Build the Art. 30 data-processing inventory consumed by
     * /admin/compliance/data-map.
     *
     * @return array<string, mixed>
     */
    public function dataMap(): array
    {
        return [
            'organization' => config('app.name', 'ParkHub'),
            'generated_at' => now()->toISOString(),
            'processing_activities' => $this->processingActivities(),
        ];
    }

    /**
     * Raw audit log rows, newest first, capped at $limit. Returns null
     * when the audit table isn't present (module off).
     *
     * The table is `audit_log` (see {@see AuditLog::$table})
     * and its columns are `target_type` / `target_id`. This method asked
     * for `audit_logs` with `resource_*` columns, so the existence check
     * never passed and the GDPR audit export was permanently empty. The
     * export's own column names are preserved by aliasing, so the CSV
     * contract does not change — only the fact that it now contains data.
     */
    public function auditLogs(int $limit): ?Collection
    {
        if (! DB::getSchemaBuilder()->hasTable('audit_log')) {
            return null;
        }

        return DB::table('audit_log')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'user_id',
                'action',
                'target_type as resource_type',
                'target_id as resource_id',
                'ip_address',
                'created_at',
            ]);
    }

    /**
     * Serialize audit log rows to the canonical CSV layout (RFC 4180
     * quoting for the action column).
     *
     * @param  iterable<int, object>  $logs
     */
    public function auditLogsCsv(iterable $logs): string
    {
        $csv = "id,user_id,action,resource_type,resource_id,ip_address,created_at\n";
        foreach ($logs as $log) {
            $csv .= implode(',', [
                $log->id,
                $log->user_id ?? '',
                '"'.str_replace('"', '""', $log->action).'"',
                $log->resource_type ?? '',
                $log->resource_id ?? '',
                $log->ip_address ?? '',
                $log->created_at,
            ])."\n";
        }

        return $csv;
    }

    /**
     * The 10-item compliance check list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function runChecks(): array
    {
        return [
            [
                'id' => 'encryption-at-rest',
                'category' => 'Security',
                'name' => 'Encryption at Rest',
                'description' => 'Database and file encryption',
                'status' => 'compliant',
                'details' => 'Application uses encrypted database connections and bcrypt password hashing',
                'recommendation' => null,
            ],
            [
                'id' => 'encryption-in-transit',
                'category' => 'Security',
                'name' => 'Encryption in Transit',
                'description' => 'TLS/HTTPS enforcement',
                'status' => config('app.env') === 'production' ? 'compliant' : 'warning',
                'details' => config('app.env') === 'production' ? 'HTTPS enforced in production' : 'Running in non-production mode',
                'recommendation' => config('app.env') === 'production' ? null : 'Enable HTTPS for production deployment',
            ],
            [
                'id' => 'access-control',
                'category' => 'Security',
                'name' => 'Access Control',
                'description' => 'Role-based access control (RBAC)',
                'status' => 'compliant',
                'details' => 'RBAC with admin/superadmin/user roles, Sanctum token authentication',
                'recommendation' => null,
            ],
            [
                'id' => 'audit-logging',
                'category' => 'Accountability',
                'name' => 'Audit Logging',
                'description' => 'Action audit trail',
                'status' => DB::getSchemaBuilder()->hasTable('audit_log') ? 'compliant' : 'non_compliant',
                'details' => DB::getSchemaBuilder()->hasTable('audit_log') ? 'Audit log table exists with action tracking' : 'Audit log table not found',
                'recommendation' => DB::getSchemaBuilder()->hasTable('audit_log') ? null : 'Enable the audit_log module',
            ],
            [
                'id' => 'data-minimization',
                'category' => 'Data Protection',
                'name' => 'Data Minimization',
                'description' => 'Only essential data collected',
                'status' => 'compliant',
                'details' => 'User profiles contain only name, email, and role. Vehicle data is optional.',
                'recommendation' => null,
            ],
            [
                'id' => 'data-portability',
                'category' => 'Data Subject Rights',
                'name' => 'Data Portability (Art. 20)',
                'description' => 'User data export capability',
                'status' => 'compliant',
                'details' => 'JSON export available via /api/v1/user/export',
                'recommendation' => null,
            ],
            [
                'id' => 'right-to-erasure',
                'category' => 'Data Subject Rights',
                'name' => 'Right to Erasure (Art. 17)',
                'description' => 'Account deletion capability',
                'status' => 'compliant',
                'details' => 'Admin can delete user accounts and associated data',
                'recommendation' => null,
            ],
            [
                'id' => 'consent-management',
                'category' => 'Lawfulness',
                'name' => 'Consent Management',
                'description' => 'User consent tracking',
                'status' => 'warning',
                'details' => 'Basic consent via registration. No granular consent management.',
                'recommendation' => 'Implement granular consent management for optional data processing',
            ],
            [
                'id' => 'dpo-appointed',
                'category' => 'Organization',
                'name' => 'Data Protection Officer',
                'description' => 'DPO designation',
                'status' => 'warning',
                'details' => 'No DPO configured in system settings',
                'recommendation' => 'Consider appointing a Data Protection Officer for organizations with >250 employees',
            ],
            [
                'id' => 'privacy-policy',
                'category' => 'Transparency',
                'name' => 'Privacy Policy',
                'description' => 'Published privacy policy',
                'status' => 'compliant',
                'details' => 'Privacy policy available at /api/v1/legal/privacy and configurable via admin settings',
                'recommendation' => null,
            ],
        ];
    }

    /**
     * Collapse the per-check statuses into a single overall status.
     * Non-compliant dominates warnings; warnings dominate compliance.
     *
     * @param  array<int, array<string, mixed>>  $checks
     */
    private function computeOverallStatus(array $checks): string
    {
        $hasNonCompliant = collect($checks)->contains('status', 'non_compliant');
        $hasWarning = collect($checks)->contains('status', 'warning');

        if ($hasNonCompliant) {
            return 'non_compliant';
        }
        if ($hasWarning) {
            return 'warning';
        }

        return 'compliant';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dataCategories(): array
    {
        return [
            ['category' => 'Identity', 'fields' => ['name', 'email'], 'sensitivity' => 'standard'],
            ['category' => 'Authentication', 'fields' => ['password_hash', 'api_tokens'], 'sensitivity' => 'high'],
            ['category' => 'Vehicle', 'fields' => ['license_plate', 'make', 'model', 'color'], 'sensitivity' => 'standard'],
            ['category' => 'Booking', 'fields' => ['lot_id', 'slot_id', 'timestamps'], 'sensitivity' => 'standard'],
            ['category' => 'Audit', 'fields' => ['ip_address', 'user_agent', 'action'], 'sensitivity' => 'standard'],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function legalBasis(): array
    {
        return [
            ['processing' => 'Account management', 'basis' => 'Art. 6(1)(b)', 'description' => 'Contract performance'],
            ['processing' => 'Parking bookings', 'basis' => 'Art. 6(1)(b)', 'description' => 'Contract performance'],
            ['processing' => 'Security logging', 'basis' => 'Art. 6(1)(f)', 'description' => 'Legitimate interest'],
            ['processing' => 'Vehicle registration', 'basis' => 'Art. 6(1)(a)', 'description' => 'Consent'],
            ['processing' => 'Payment processing', 'basis' => 'Art. 6(1)(b)', 'description' => 'Contract performance'],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function retentionPeriods(): array
    {
        return [
            ['data_type' => 'User accounts', 'period' => 'Account lifetime + 30 days'],
            ['data_type' => 'Booking records', 'period' => '12 months'],
            ['data_type' => 'Audit logs', 'period' => '90 days'],
            ['data_type' => 'Payment records', 'period' => '10 years (tax)'],
            ['data_type' => 'Session data', 'period' => '24 hours'],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function subProcessors(): array
    {
        return [
            ['name' => 'Stripe', 'purpose' => 'Payment processing', 'location' => 'US/EU', 'safeguards' => 'DPA, SCCs'],
        ];
    }

    /**
     * Derive the technical & organizational measures summary from the
     * live check list. Two rows are static booleans (backup encryption
     * and privacy-by-design are always true, incident-response-plan
     * and regular-audits are always pending).
     *
     * @param  array<int, array<string, mixed>>  $checks
     * @return array<string, bool>
     */
    private function tomSummary(array $checks): array
    {
        $isCompliant = fn (string $id) => collect($checks)->firstWhere('id', $id)['status'] === 'compliant';

        return [
            'encryption_at_rest' => $isCompliant('encryption-at-rest'),
            'encryption_in_transit' => $isCompliant('encryption-in-transit'),
            'access_control' => $isCompliant('access-control'),
            'audit_logging' => $isCompliant('audit-logging'),
            'data_minimization' => $isCompliant('data-minimization'),
            'backup_encryption' => true,
            'incident_response_plan' => false,
            'dpo_appointed' => $isCompliant('dpo-appointed'),
            'privacy_by_design' => true,
            'regular_audits' => false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function processingActivities(): array
    {
        return [
            [
                'name' => 'User Account Management',
                'purpose' => 'Authentication and authorization for parking management',
                'data_subjects' => ['employees', 'visitors'],
                'data_categories' => ['name', 'email', 'role', 'password_hash'],
                'legal_basis' => 'Art. 6(1)(b) - Contract performance',
                'retention' => 'Duration of account + 30 days',
                'recipients' => ['Internal administrators'],
            ],
            [
                'name' => 'Booking Management',
                'purpose' => 'Parking spot reservation and allocation',
                'data_subjects' => ['employees', 'visitors'],
                'data_categories' => ['user_id', 'lot_id', 'slot_id', 'timestamps'],
                'legal_basis' => 'Art. 6(1)(b) - Contract performance',
                'retention' => '12 months after booking date',
                'recipients' => ['Internal administrators', 'Lot managers'],
            ],
            [
                'name' => 'Vehicle Registration',
                'purpose' => 'Vehicle identification for parking access',
                'data_subjects' => ['employees'],
                'data_categories' => ['license_plate', 'make', 'model', 'color'],
                'legal_basis' => 'Art. 6(1)(a) - Consent',
                'retention' => 'Until vehicle removed by user',
                'recipients' => ['Internal administrators'],
            ],
            [
                'name' => 'Audit Logging',
                'purpose' => 'Security and accountability tracking',
                'data_subjects' => ['all users'],
                'data_categories' => ['user_id', 'action', 'ip_address', 'timestamp'],
                'legal_basis' => 'Art. 6(1)(f) - Legitimate interest (security)',
                'retention' => '90 days',
                'recipients' => ['Security administrators'],
            ],
            [
                'name' => 'Payment Processing',
                'purpose' => 'Billing for parking services',
                'data_subjects' => ['employees', 'visitors'],
                'data_categories' => ['payment_method', 'amount', 'invoice_data'],
                'legal_basis' => 'Art. 6(1)(b) - Contract performance',
                'retention' => '10 years (tax requirement)',
                'recipients' => ['Payment processor (Stripe)', 'Tax authorities'],
            ],
        ];
    }
}
