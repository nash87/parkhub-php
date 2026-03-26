<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingCompoundIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookings_table_has_slot_status_start_end_compound_index(): void
    {
        $indexes = Schema::getIndexes('bookings');
        $names = array_column($indexes, 'name');

        $this->assertContains(
            'bookings_slot_status_start_end_index',
            $names,
            'Expected compound index bookings(slot_id, status, start_time, end_time) to exist.'
        );
    }

    public function test_bookings_table_has_lot_status_start_end_compound_index(): void
    {
        $indexes = Schema::getIndexes('bookings');
        $names = array_column($indexes, 'name');

        $this->assertContains(
            'bookings_lot_status_start_end_index',
            $names,
            'Expected compound index bookings(lot_id, status, start_time, end_time) to exist.'
        );
    }

    public function test_bookings_table_has_user_status_compound_index(): void
    {
        $indexes = Schema::getIndexes('bookings');
        $columnSets = array_column($indexes, 'columns');

        $this->assertContains(
            ['user_id', 'status'],
            $columnSets,
            'Expected compound index bookings(user_id, status) to exist.'
        );
    }
}

