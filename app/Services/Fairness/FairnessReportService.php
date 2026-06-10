<?php

declare(strict_types=1);

namespace App\Services\Fairness;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Services\Retention\RetentionClass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Betriebsrat fairness & transparency reporting service (P0-2).
 *
 * Provides two aggregation surfaces:
 *  1. allocationReport() — aggregate-only fairness metrics over a date window,
 *     including Gini coefficient, per-user allocation-frequency buckets
 *     (k-anonymised), denial reasons by category, and booking-to-allocation ratio.
 *  2. dataCollectionDisclosure() — machine-readable §87 BetrVG disclosure
 *     derived from the RetentionClass registry.
 *
 * Privacy rules:
 *  - No individual user data is ever returned.
 *  - k-anonymity: any bucket with fewer than K_ANON_THRESHOLD users is
 *    folded into an "other (<K_ANON_THRESHOLD)" catch-all bucket.
 */
final class FairnessReportService
{
    /** Minimum cohort size below which a bucket is folded into "other". */
    public const int K_ANON_THRESHOLD = 5;

    /**
     * Allocation frequency buckets applied to per-user counts.
     *
     * Each bucket is a half-open interval [min, max) except the last which is
     * open-ended.  Label is human-readable for the API consumer.
     *
     * Note: key 'none' maps to 0-allocation users; displayed as '0' in the API.
     * We avoid the literal key '0' because PHP coerces it to integer 0 in arrays,
     * which confuses static-analysis tooling.
     *
     * @var array<string, array{min: int, max: int|null, label: string}>
     */
    private const array FREQUENCY_BUCKETS = [
        'none' => ['min' => 0, 'max' => 1, 'label' => '0'],
        '1-2' => ['min' => 1, 'max' => 3, 'label' => '1-2'],
        '3-5' => ['min' => 3, 'max' => 6, 'label' => '3-5'],
        '6+' => ['min' => 6, 'max' => null, 'label' => '6+'],
    ];

    /**
     * Build the fairness report for the given date window.
     *
     * @return array{
     *   window: array{from: string, to: string},
     *   total_allocations: int,
     *   unique_users_allocated: int,
     *   allocation_frequency_buckets: array<string, int>,
     *   denial_reasons: array<string, int>,
     *   booking_to_allocation_ratio: float|null,
     *   gini_coefficient: float|null,
     *   k_anonymity_threshold: int,
     * }
     */
    public function allocationReport(Carbon $from, Carbon $to): array
    {
        $allocationEventTypes = ['RecommendationServed', 'ExactCoverAllocationServed'];

        // Per-user allocation counts in the window — never returned raw.
        /** @var array<string, int> $perUser  keyed by user_id */
        $perUser = AuditLog::query()
            ->whereIn('event_type', $allocationEventTypes)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $totalAllocations = array_sum($perUser);
        $uniqueUsersAllocated = count($perUser);

        $frequencyBuckets = $this->buildFrequencyBuckets($perUser);
        $gini = $this->computeGini(array_values($perUser));

        // Denial reasons from ExactCoverAllocationServed where status != 'solved'.
        $denialReasons = $this->aggregateDenialReasons($from, $to);

        // Booking-to-allocation ratio: total bookings in window / total allocations.
        $totalBookings = Booking::whereBetween('created_at', [$from, $to])->count();
        $bookingToAllocationRatio = $totalAllocations > 0
            ? round($totalBookings / $totalAllocations, 4)
            : null;

        return [
            'window' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'total_allocations' => $totalAllocations,
            'unique_users_allocated' => $uniqueUsersAllocated,
            'allocation_frequency_buckets' => $frequencyBuckets,
            'denial_reasons' => $denialReasons,
            'booking_to_allocation_ratio' => $bookingToAllocationRatio,
            'gini_coefficient' => $gini,
            'k_anonymity_threshold' => self::K_ANON_THRESHOLD,
        ];
    }

    /**
     * Build §87 BetrVG machine-readable disclosure from the RetentionClass registry.
     *
     * Returns one entry per RetentionClass case plus surface notes on which
     * booking / check-in / charging interactions produce data in each class.
     *
     * @return array{
     *   disclosure_version: string,
     *   legal_basis: string,
     *   classes: array<int, array{
     *     class: string,
     *     purpose: string,
     *     default_ttl_days: int,
     *     statutory_min_days: int|null,
     *     is_legal_hold: bool,
     *     surfaces: array<int, string>,
     *   }>,
     *   no_covert_monitoring: bool,
     *   no_performance_evaluation: bool,
     * }
     */
    public function dataCollectionDisclosure(): array
    {
        $classes = [];

        foreach (RetentionClass::cases() as $class) {
            $classes[] = [
                'class' => $class->value,
                'purpose' => $this->classPurpose($class),
                'default_ttl_days' => $class->defaultTtlDays(),
                'statutory_min_days' => $class->statutoryMinDays(),
                'is_legal_hold' => $class->isLegalHold(),
                'surfaces' => $this->classSurfaces($class),
            ];
        }

        return [
            'disclosure_version' => '1.0',
            'legal_basis' => '§87 Abs. 1 Nr. 6 BetrVG — Einführung und Anwendung technischer Einrichtungen, die dazu bestimmt sind, das Verhalten oder die Leistung der Arbeitnehmer zu überwachen.',
            'classes' => $classes,
            'no_covert_monitoring' => true,
            'no_performance_evaluation' => true,
        ];
    }

    /**
     * Aggregate per-user counts into k-anonymised frequency buckets.
     *
     * Buckets with fewer than K_ANON_THRESHOLD users are folded into
     * an "other (<K_ANON_THRESHOLD)" entry so no individual can be singled out.
     *
     * Gini coefficient formula (Brown, 1994 / UNDP standard):
     *
     *   Sort allocation counts x_1 ≤ x_2 ≤ … ≤ x_n ascending.
     *   Assign rank i = 1 … n (1 = lowest count).
     *
     *   G = ( 2 * Σ(i * x_i) - (n+1) * Σ(x_i) ) / ( n * Σ(x_i) )
     *
     * Properties:
     *   - All equal → numerator = 0 → G = 0.0
     *   - One user takes everything → G = (n-1)/n → approaches 1 as n grows.
     *   - 0 ≤ G ≤ (n-1)/n < 1 for finite populations.
     *
     * Returns null when the user set is empty (division by zero undefined).
     *
     * @param  array<string, int>  $perUser  user_id → allocation count
     * @return array<string, int>
     */
    private function buildFrequencyBuckets(array $perUser): array
    {
        // raw bucket: internal key → user count
        $raw = [];

        foreach (self::FREQUENCY_BUCKETS as $key => $range) {
            $raw[$key] = 0;
        }

        foreach ($perUser as $count) {
            foreach (self::FREQUENCY_BUCKETS as $key => $range) {
                $inBucket = $count >= $range['min']
                    && ($range['max'] === null || $count < $range['max']);
                if ($inBucket) {
                    $raw[$key]++;
                    break;
                }
            }
        }

        // k-anonymity: fold thin buckets into "other (<K_ANON_THRESHOLD)".
        // Use the user-facing label (not the internal key) for the output.
        $threshold = self::K_ANON_THRESHOLD;
        $result = [];
        $otherCount = 0;

        foreach ($raw as $key => $userCount) {
            $label = self::FREQUENCY_BUCKETS[$key]['label'];
            if ($userCount > 0 && $userCount < $threshold) {
                $otherCount += $userCount;
            } else {
                $result[$label] = $userCount;
            }
        }

        if ($otherCount > 0) {
            $result["other (<{$threshold})"] = $otherCount;
        }

        return $result;
    }

    /**
     * Compute the Gini coefficient over a list of allocation counts.
     *
     * Formula (see docblock on buildFrequencyBuckets for derivation):
     *   G = ( 2 * Σ(rank_i * x_i) - (n+1) * Σ(x_i) ) / ( n * Σ(x_i) )
     *
     * Returns null for empty input (undefined) or when all counts are zero
     * (every user has zero allocations — perfectly equal, but ratio is 0/0).
     *
     * @param  array<int, int>  $counts  raw allocation counts, any order
     */
    public function computeGini(array $counts): ?float
    {
        $counts = array_values(array_filter($counts, fn ($v) => $v >= 0));
        $n = count($counts);

        if ($n === 0) {
            return null;
        }

        sort($counts);
        $sumX = array_sum($counts);

        if ($sumX === 0) {
            return 0.0;
        }

        $weightedSum = 0;
        foreach ($counts as $i => $x) {
            $rank = $i + 1; // 1-indexed
            $weightedSum += $rank * $x;
        }

        $gini = (2.0 * $weightedSum - ($n + 1) * $sumX) / ($n * $sumX);

        // Clamp to [0, 1] to absorb floating-point noise.
        return max(0.0, min(1.0, round($gini, 6)));
    }

    /**
     * Aggregate denial reasons from ExactCoverAllocationServed audit rows
     * where `details.status` is not 'solved'.
     *
     * @return array<string, int>
     */
    private function aggregateDenialReasons(Carbon $from, Carbon $to): array
    {
        $rows = AuditLog::query()
            ->where('event_type', 'ExactCoverAllocationServed')
            ->whereBetween('created_at', [$from, $to])
            ->select('details')
            ->get();

        $counts = [];

        foreach ($rows as $log) {
            $raw = $log->getAttribute('details');
            $details = is_array($raw) ? $raw : [];
            $status = isset($details['status']) && is_string($details['status'])
                ? $details['status']
                : 'unknown';

            if ($status === 'solved') {
                continue;
            }

            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    /** Human-readable purpose description per retention class. */
    private function classPurpose(RetentionClass $class): string
    {
        return match ($class) {
            RetentionClass::OperationalPresence => 'Betriebliche Anwesenheitserfassung für Parkplatzverwaltung.',
            RetentionClass::BookingHistory => 'Buchungshistorie für Rechnungsstellung und Nutzerservice.',
            RetentionClass::SecurityAuditLog => 'Sicherheitsprotokoll für Systemintegrität und Datenschutz-Compliance.',
            RetentionClass::HrLabour => 'Arbeitsrechtlich relevante Daten gemäß BGB §195.',
            RetentionClass::AnprRaw => 'Rohdaten der automatischen Kennzeichenerkennung (ANPR).',
            RetentionClass::EvSession => 'Ladesitzungsdaten für Abrechnung und Betrieb.',
            RetentionClass::BillingFiscal => 'Steuerlich relevante Rechnungs- und Zahlungsdaten gemäß HGB §257 (GoBD).',
        };
    }

    /**
     * Which system surfaces generate data in this retention class.
     *
     * @return array<int, string>
     */
    private function classSurfaces(RetentionClass $class): array
    {
        return match ($class) {
            RetentionClass::OperationalPresence => ['booking_check_in', 'booking_check_out', 'lobby_display'],
            RetentionClass::BookingHistory => ['booking_create', 'booking_modify', 'booking_cancel'],
            RetentionClass::SecurityAuditLog => ['auth_login', 'auth_failed', 'admin_action', 'api_key_use'],
            RetentionClass::HrLabour => ['admin_user_management', 'role_assignment'],
            RetentionClass::AnprRaw => ['anpr_camera_read', 'vehicle_entry', 'vehicle_exit'],
            RetentionClass::EvSession => ['ev_charging_start', 'ev_charging_stop', 'ev_charging_payment'],
            RetentionClass::BillingFiscal => ['payment_charge', 'invoice_issue', 'refund'],
        };
    }
}
