<?php

namespace Tests\Unit\Models;

use App\Models\Favorite;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $favorite = new Favorite;
        $fillable = $favorite->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('slot_id', $fillable);
    }

    public function test_slot_relation_defined(): void
    {
        $favorite = new Favorite;
        $relation = $favorite->slot();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }
}
