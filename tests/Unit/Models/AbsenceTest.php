<?php

namespace Tests\Unit\Models;

use App\Models\Absence;
use PHPUnit\Framework\TestCase;

class AbsenceTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $absence = new Absence();
        $fillable = $absence->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('absence_type', $fillable);
        $this->assertContains('start_date', $fillable);
        $this->assertContains('end_date', $fillable);
        $this->assertContains('note', $fillable);
        $this->assertContains('source', $fillable);
    }

    public function test_user_relation_defined(): void
    {
        $absence = new Absence();
        $relation = $absence->user();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }
}
