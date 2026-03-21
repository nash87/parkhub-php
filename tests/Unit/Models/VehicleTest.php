<?php

namespace Tests\Unit\Models;

use App\Models\Vehicle;
use PHPUnit\Framework\TestCase;

class VehicleTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $vehicle = new Vehicle();
        $fillable = $vehicle->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('plate', $fillable);
        $this->assertContains('make', $fillable);
        $this->assertContains('model', $fillable);
        $this->assertContains('color', $fillable);
        $this->assertContains('is_default', $fillable);
        $this->assertContains('photo_url', $fillable);
    }

    public function test_user_relation_defined(): void
    {
        $vehicle = new Vehicle();
        $relation = $vehicle->user();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }
}
