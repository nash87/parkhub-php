<?php

namespace Tests\Unit\Models;

use App\Models\Favorite;
use PHPUnit\Framework\TestCase;

class FavoriteTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $favorite = new Favorite();
        $fillable = $favorite->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('slot_id', $fillable);
    }

    public function test_slot_relation_defined(): void
    {
        $favorite = new Favorite();
        $relation = $favorite->slot();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }
}
