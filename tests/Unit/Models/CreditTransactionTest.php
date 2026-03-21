<?php

namespace Tests\Unit\Models;

use App\Models\CreditTransaction;
use PHPUnit\Framework\TestCase;

class CreditTransactionTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $tx = new CreditTransaction();
        $fillable = $tx->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('booking_id', $fillable);
        $this->assertContains('amount', $fillable);
        $this->assertContains('type', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('granted_by', $fillable);
    }

    public function test_user_relation_defined(): void
    {
        $tx = new CreditTransaction();
        $relation = $tx->user();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    public function test_booking_relation_defined(): void
    {
        $tx = new CreditTransaction();
        $relation = $tx->booking();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }
}
