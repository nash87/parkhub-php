<?php

namespace Tests\Integration;

use App\Models\Absence;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;

class GdprComplianceTest extends IntegrationTestCase
{
    // ── Full GDPR lifecycle: create data -> export -> anonymize -> verify ─

    public function test_full_gdpr_lifecycle(): void
    {
        // 1. Create user with full PII
        $user = User::factory()->create([
            'name' => 'Hans Mueller',
            'email' => 'hans.mueller@example.de',
            'username' => 'hansm',
            'department' => 'Engineering',
            'phone' => '+49 171 1234567',
            'password' => bcrypt('GdprPass123'),
            'credits_balance' => 30,
        ]);
        $token = $this->createTokenForUser($user);

        // 2. Create related data
        $lot = $this->createLotWithSlots(3);
        $slot = $lot->slots()->first();

        Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'B-HM 1234',
            'make' => 'BMW',
            'model' => '3er',
            'color' => 'Blue',
        ]);

        Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'M-XY 5678',
            'make' => 'VW',
            'model' => 'Golf',
            'color' => 'Silver',
        ]);

        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->addDay()->setHour(8),
            'end_time' => now()->addDay()->setHour(18),
            'booking_type' => 'single',
            'status' => 'confirmed',
            'vehicle_plate' => 'B-HM 1234',
            'notes' => 'Hans needs wheelchair access',
        ]);

        Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'homeoffice',
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
        ]);

        // 3. Art. 15 — Export: verify completeness
        $exportResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users/me/export');
        $exportResponse->assertStatus(200);

        $exportData = $exportResponse->json('data');

        // Verify export contains all categories
        $this->assertArrayHasKey('exported_at', $exportData);
        $this->assertArrayHasKey('profile', $exportData);
        $this->assertArrayHasKey('bookings', $exportData);
        $this->assertArrayHasKey('vehicles', $exportData);
        $this->assertArrayHasKey('absences', $exportData);

        // Verify profile data matches
        $this->assertEquals($user->id, $exportData['profile']['id']);
        $this->assertEquals('Hans Mueller', $exportData['profile']['name']);
        $this->assertEquals('hans.mueller@example.de', $exportData['profile']['email']);

        // Verify all bookings included
        $this->assertCount(1, $exportData['bookings']);
        $this->assertEquals($booking->id, $exportData['bookings'][0]['id']);

        // Verify all vehicles included
        $this->assertCount(2, $exportData['vehicles']);

        // Verify absences included
        $this->assertCount(1, $exportData['absences']);

        // 4. Art. 17 — Anonymize: erase PII
        $anonymizeResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users/me/anonymize', ['password' => 'GdprPass123']);
        $anonymizeResponse->assertStatus(200);

        // 5. Verify PII is removed from user record
        $anonymized = User::find($user->id);
        $this->assertNotNull($anonymized, 'User record should still exist');
        $this->assertEquals('[Gelöschter Nutzer]', $anonymized->name);
        $this->assertStringContainsString('@deleted.invalid', $anonymized->email);
        $this->assertNull($anonymized->department);
        $this->assertFalse($anonymized->is_active);

        // 6. Verify booking records are anonymized (PII stripped)
        $anonymizedBooking = Booking::find($booking->id);
        $this->assertNotNull($anonymizedBooking, 'Booking should still exist for audit');
        $this->assertEquals('[GELÖSCHT]', $anonymizedBooking->vehicle_plate);
        $this->assertNull($anonymizedBooking->notes);

        // 7. Verify vehicles are deleted (pure PII, no audit value)
        $this->assertDatabaseMissing('vehicles', ['user_id' => $user->id]);

        // 8. Verify absences are deleted
        $this->assertDatabaseMissing('absences', ['user_id' => $user->id]);
    }

    // ── Export does not leak other users' data ──────────────────────────

    public function test_export_only_includes_own_data(): void
    {
        $userA = User::factory()->create(['role' => 'user']);
        $userB = User::factory()->create(['role' => 'user']);

        Vehicle::create(['user_id' => $userA->id, 'plate' => 'A-AA 1111', 'make' => 'VW']);
        Vehicle::create(['user_id' => $userB->id, 'plate' => 'B-BB 2222', 'make' => 'BMW']);

        $tokenA = $this->createTokenForUser($userA);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/v1/users/me/export');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data['vehicles']);
        $this->assertEquals('A-AA 1111', $data['vehicles'][0]['plate']);
    }

    // ── Anonymize requires correct password ──────────────────────────────

    public function test_anonymize_fails_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'name' => 'Protected User',
            'password' => bcrypt('RealPassword'),
        ]);
        $token = $this->createTokenForUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users/me/anonymize', ['password' => 'WrongPassword']);

        $response->assertStatus(403);

        // User data should remain intact
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Protected User']);
    }

    // ── Anonymize requires auth ──────────────────────────────────────────

    public function test_anonymize_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/users/me/anonymize', ['password' => 'test']);
        $response->assertStatus(401);
    }

    public function test_export_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/users/me/export');
        $response->assertStatus(401);
    }

    // ── Delete account (soft delete) ─────────────────────────────────────

    public function test_account_deletion_soft_deletes_user(): void
    {
        $user = User::factory()->create(['password' => bcrypt('DeleteMe123')]);
        $token = $this->createTokenForUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/users/me/delete', ['password' => 'DeleteMe123']);

        $response->assertStatus(200);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_account_deletion_requires_correct_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('CorrectPassword')]);
        $token = $this->createTokenForUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/users/me/delete', ['password' => 'WrongPassword']);

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    // ── Multiple bookings preserved with PII stripped ─────────────────────

    public function test_anonymize_strips_pii_from_all_bookings(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Strip123')]);
        $lot = $this->createLotWithSlots(3);
        $slots = $lot->slots;

        foreach ($slots as $i => $slot) {
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDays($i + 1)->setHour(9),
                'end_time' => now()->addDays($i + 1)->setHour(17),
                'booking_type' => 'single',
                'status' => 'confirmed',
                'vehicle_plate' => "B-X{$i} 1234",
                'notes' => "User note {$i}",
            ]);
        }

        $token = $this->createTokenForUser($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users/me/anonymize', ['password' => 'Strip123'])
            ->assertStatus(200);

        // All bookings should have PII stripped
        $bookings = Booking::where('user_id', $user->id)->get();
        $this->assertCount(3, $bookings);

        foreach ($bookings as $booking) {
            $this->assertEquals('[GELÖSCHT]', $booking->vehicle_plate);
            $this->assertNull($booking->notes);
        }
    }
}
