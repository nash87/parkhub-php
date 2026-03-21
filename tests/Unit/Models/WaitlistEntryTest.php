<?php

namespace Tests\Unit\Models;

use App\Models\WaitlistEntry;
use PHPUnit\Framework\TestCase;

class WaitlistEntryTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $entry = new WaitlistEntry();
        $fillable = $entry->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('lot_id', $fillable);
        $this->assertContains('slot_id', $fillable);
        $this->assertContains('notified_at', $fillable);
    }

    public function test_user_relation_defined(): void
    {
        $entry = new WaitlistEntry();
        $relation = $entry->user();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    public function test_lot_relation_defined(): void
    {
        $entry = new WaitlistEntry();
        $relation = $entry->lot();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    public function test_slot_relation_defined(): void
    {
        $entry = new WaitlistEntry();
        $relation = $entry->slot();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }
}
