<?php

namespace Tests\Unit\Models;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class ZoneTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $zone = new Zone;
        $fillable = $zone->getFillable();

        $this->assertContains('lot_id', $fillable);
        $this->assertContains('name', $fillable);
        $this->assertContains('color', $fillable);
        $this->assertContains('description', $fillable);
    }

    public function test_lot_relation_defined(): void
    {
        $zone = new Zone;
        $relation = $zone->lot();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_slots_relation_defined(): void
    {
        $zone = new Zone;
        $relation = $zone->slots();
        $this->assertInstanceOf(HasMany::class, $relation);
    }
}
