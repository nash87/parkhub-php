<?php

namespace Tests\Unit\Models;

use App\Models\BookingNote;
use PHPUnit\Framework\TestCase;

class BookingNoteTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $note = new BookingNote();
        $fillable = $note->getFillable();

        $this->assertContains('booking_id', $fillable);
        $this->assertContains('user_id', $fillable);
        $this->assertContains('note', $fillable);
    }
}
