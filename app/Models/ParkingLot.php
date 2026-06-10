<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property ?string $address
 * @property ?string $latitude
 * @property ?string $longitude
 * @property ?string $center_lat
 * @property ?string $center_lng
 * @property ?int $geofence_radius_m
 * @property int $total_slots
 * @property int $available_slots
 * @property ?array<string, mixed> $layout
 * @property string $status
 * @property ?string $hourly_rate
 * @property ?string $daily_max
 * @property ?string $monthly_pass
 * @property string $currency
 * @property ?array<string, mixed> $operating_hours
 * @property ?array<string, mixed> $dynamic_pricing_rules
 * @property ?int $check_in_deadline_minutes
 * @property ?int $claim_window_minutes
 * @property ?string $tenant_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, ParkingSlot> $slots
 * @property-read Collection<int, Zone> $zones
 * @property-read Collection<int, Booking> $bookings
 * @property-read ?Tenant $tenant
 */
class ParkingLot extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = ['name', 'address', 'latitude', 'longitude', 'center_lat', 'center_lng', 'geofence_radius_m', 'total_slots', 'available_slots', 'layout', 'status', 'hourly_rate', 'daily_max', 'monthly_pass', 'currency', 'operating_hours', 'dynamic_pricing_rules', 'check_in_deadline_minutes', 'claim_window_minutes', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'total_slots' => 'integer',
            'available_slots' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'center_lat' => 'decimal:7',
            'center_lng' => 'decimal:7',
            'geofence_radius_m' => 'integer',
            'hourly_rate' => 'decimal:2',
            'daily_max' => 'decimal:2',
            'monthly_pass' => 'decimal:2',
            'operating_hours' => 'array',
            'dynamic_pricing_rules' => 'array',
            'check_in_deadline_minutes' => 'integer',
            'claim_window_minutes' => 'integer',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ParkingSlot::class, 'lot_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class, 'lot_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'lot_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if the lot is open at a given datetime based on operating_hours.
     * Returns true if no operating hours are configured (always open).
     */
    public function isOpenAt(\DateTimeInterface $dateTime): bool
    {
        if (empty($this->operating_hours)) {
            return true;
        }

        $dayName = strtolower($dateTime->format('l'));
        $dayHours = $this->operating_hours[$dayName] ?? null;

        if ($dayHours === null) {
            return false; // Day not listed = closed
        }

        if (isset($dayHours['closed']) && $dayHours['closed']) {
            return false;
        }

        $open = $dayHours['open'] ?? null;
        $close = $dayHours['close'] ?? null;

        if (! $open || ! $close) {
            return true; // No hours specified = 24h open
        }

        $time = $dateTime->format('H:i');

        return $time >= $open && $time < $close;
    }
}
