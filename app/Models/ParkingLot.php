<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingLot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'address', 'latitude', 'longitude', 'center_lat', 'center_lng', 'geofence_radius_m', 'total_slots', 'available_slots', 'layout', 'status', 'hourly_rate', 'daily_max', 'monthly_pass', 'currency', 'operating_hours', 'dynamic_pricing_rules', 'tenant_id'];

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
        ];
    }

    public function slots()
    {
        return $this->hasMany(ParkingSlot::class, 'lot_id');
    }

    public function zones()
    {
        return $this->hasMany(Zone::class, 'lot_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'lot_id');
    }

    public function tenant()
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
