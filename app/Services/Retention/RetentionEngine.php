<?php

declare(strict_types=1);

namespace App\Services\Retention;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * GDPR retention engine — surface registry + purge executor.
 *
 * Slice 1 surface: AuditLog rows keyed by
 * `details->retention_deletion_class`.
 *
 * Fail-safe invariant: only rows whose `retention_deletion_class` value
 * maps to a known RetentionClass case are ever deleted. Rows with unknown
 * or absent class values are untouched regardless of age.
 */
final class RetentionEngine
{
    private const SETTING_PREFIX = 'retention_ttl_days_';

    private const CHUNK_SIZE = 1000;

    /**
     * Effective TTL in days for a class (override > default).
     */
    public function getTtlDays(RetentionClass $class): int
    {
        $override = Setting::get(self::SETTING_PREFIX.$class->value);

        return $override !== null ? (int) $override : $class->defaultTtlDays();
    }

    /**
     * Persist a TTL override for a class.
     *
     * @throws \InvalidArgumentException when $days would breach the statutory minimum.
     */
    public function setTtlDays(RetentionClass $class, int $days): void
    {
        if ($class->isLegalHold()) {
            $min = $class->statutoryMinDays();
            if ($days < $min) {
                throw new \InvalidArgumentException(
                    "TTL {$days} days is below the statutory minimum {$min} days for class {$class->value}."
                );
            }
        }

        Setting::set(self::SETTING_PREFIX.$class->value, (string) $days);
    }

    /**
     * All effective policies keyed by class value.
     *
     * @return array<string, int>
     */
    public function getPolicies(): array
    {
        $policies = [];
        foreach (RetentionClass::cases() as $class) {
            $policies[$class->value] = $this->getTtlDays($class);
        }

        return $policies;
    }

    /**
     * Run the retention purge across all registered surfaces.
     *
     * When $dryRun is true: count expired rows per class, return counts,
     * delete nothing, write no evidence entries.
     *
     * When $dryRun is false: delete expired rows in chunks, write one
     * evidence AuditLog entry per class that had records_count > 0.
     *
     * @return list<array{class: string, record_count: int, oldest_deleted_at: ?string, newest_deleted_at: ?string, dry_run: bool}>
     */
    public function purge(bool $dryRun = false): array
    {
        return array_map(
            fn (RetentionClass $class) => $this->purgeClass($class, $dryRun),
            RetentionClass::cases(),
        );
    }

    /**
     * Retrieve evidence log entries (RetentionPurgeRun audit events).
     *
     * @return list<array<string, mixed>>
     */
    public function getEvidence(int $limit = 100): array
    {
        return AuditLog::where('event_type', 'RetentionPurgeRun')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (AuditLog $log) {
                // details is cast to array via the model's casts() METHOD,
                // which this larastan version does not infer (it only reads
                // the $casts property and falls back to the DB column type).
                // getAttribute() returns mixed, so the narrowing below is a
                // genuine runtime guard, not a type-checker workaround.
                $raw = $log->getAttribute('details');
                $details = is_array($raw) ? $raw : [];

                return [
                    'id' => $log->id,
                    'purged_class' => $details['purged_class'] ?? null,
                    'record_count' => $details['record_count'] ?? 0,
                    'oldest_deleted_at' => $details['oldest_deleted_at'] ?? null,
                    'newest_deleted_at' => $details['newest_deleted_at'] ?? null,
                    'dry_run' => $details['dry_run'] ?? false,
                    'occurred_at' => $log->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Process one retention class: count/delete expired AuditLog rows.
     *
     * @return array{class: string, record_count: int, oldest_deleted_at: ?string, newest_deleted_at: ?string, dry_run: bool}
     */
    private function purgeClass(RetentionClass $class, bool $dryRun): array
    {
        $cutoff = now()->subDays($this->getTtlDays($class));

        $count = AuditLog::where('details->retention_deletion_class', $class->value)
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($count === 0) {
            return $this->emptyResult($class->value, $dryRun);
        }

        $oldestRaw = AuditLog::where('details->retention_deletion_class', $class->value)
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->value('created_at');

        $newestRaw = AuditLog::where('details->retention_deletion_class', $class->value)
            ->where('created_at', '<', $cutoff)
            ->orderByDesc('created_at')
            ->value('created_at');

        $oldest = $oldestRaw !== null ? Carbon::parse($oldestRaw)->toIso8601String() : null;
        $newest = $newestRaw !== null ? Carbon::parse($newestRaw)->toIso8601String() : null;

        if ($dryRun) {
            return [
                'class' => $class->value,
                'record_count' => $count,
                'oldest_deleted_at' => $oldest,
                'newest_deleted_at' => $newest,
                'dry_run' => true,
            ];
        }

        $deleted = $this->deleteInChunks($class, $cutoff);

        $this->writeEvidence($class, $deleted, $oldest, $newest);

        return [
            'class' => $class->value,
            'record_count' => $deleted,
            'oldest_deleted_at' => $oldest,
            'newest_deleted_at' => $newest,
            'dry_run' => false,
        ];
    }

    /** @return array{class: string, record_count: int, oldest_deleted_at: null, newest_deleted_at: null, dry_run: bool} */
    private function emptyResult(string $classValue, bool $dryRun): array
    {
        return [
            'class' => $classValue,
            'record_count' => 0,
            'oldest_deleted_at' => null,
            'newest_deleted_at' => null,
            'dry_run' => $dryRun,
        ];
    }

    private function deleteInChunks(RetentionClass $class, Carbon $cutoff): int
    {
        $deleted = 0;

        while (true) {
            $ids = AuditLog::where('details->retention_deletion_class', $class->value)
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(self::CHUNK_SIZE)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $n = AuditLog::whereIn('id', $ids)->delete();
            $deleted += $n;

            if ($n === 0) {
                break; // defensive: avoid infinite loop on partial failure
            }
        }

        return $deleted;
    }

    /**
     * Write one evidence AuditLog entry — tagged as security_audit_log so it
     * inherits a 180-day retention window and is itself eventually purged.
     * Never includes content from the deleted rows.
     */
    private function writeEvidence(
        RetentionClass $class,
        int $recordCount,
        ?string $oldest,
        ?string $newest,
    ): void {
        AuditLog::log([
            'action' => 'RetentionPurgeRun',
            'event_type' => 'RetentionPurgeRun',
            'details' => [
                'retention_deletion_class' => RetentionClass::SecurityAuditLog->value,
                'purged_class' => $class->value,
                'record_count' => $recordCount,
                'oldest_deleted_at' => $oldest,
                'newest_deleted_at' => $newest,
                'dry_run' => false,
            ],
        ]);
    }
}
