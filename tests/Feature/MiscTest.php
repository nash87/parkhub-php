<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiscTest extends TestCase
{
    use RefreshDatabase;

    // Email settings tests (via /api/admin/email-settings — admin only)
    public function test_email_settings_requires_auth(): void
    {
        $this->getJson('/api/admin/email-settings')
            ->assertStatus(401);
    }

    public function test_non_admin_cannot_get_email_settings(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/email-settings')
            ->assertStatus(403);
    }

    public function test_admin_can_get_email_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/email-settings');

        $response->assertStatus(200)
            ->assertJsonStructure(['smtp_host', 'smtp_port', 'smtp_user', 'smtp_from', 'smtp_enabled']);
    }

    public function test_admin_can_update_email_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/email-settings', [
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
                'smtp_user' => 'noreply@example.com',
                'smtp_from' => 'noreply@example.com',
                'smtp_enabled' => true,
            ]);

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_update_email_settings(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/email-settings', [
                'smtp_host' => 'smtp.example.com',
            ])
            ->assertStatus(403);
    }

    // QR Code endpoint — /api/qr/{bookingId}
    public function test_qr_code_requires_auth(): void
    {
        $this->getJson('/api/qr/some-id')
            ->assertStatus(401);
    }

    public function test_user_can_get_qr_code_for_booking(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'QR Test Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'A1',
            'status' => 'available',
        ]);
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'QR Test Lot',
            'slot_number' => 'A1',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/qr/'.$booking->id);

        $response->assertStatus(200)
            ->assertJsonStructure(['qr_data', 'booking']);
    }

    // VAPID public key endpoint (public)
    public function test_vapid_public_key_is_public(): void
    {
        $response = $this->getJson('/api/v1/push/vapid-key');
        $response->assertStatus(200)
            ->assertJsonStructure(['publicKey']);
    }

    public function test_vapid_public_key_returns_configured_value(): void
    {
        Setting::set('vapid_public_key', 'test-public-key-123');

        $response = $this->getJson('/api/v1/push/vapid-key');
        $response->assertStatus(200)
            ->assertJsonPath('publicKey', 'test-public-key-123');
    }

    // Email settings via /api/email/settings
    public function test_email_settings_endpoint_requires_auth(): void
    {
        $this->getJson('/api/email/settings')
            ->assertStatus(401);
    }

    public function test_non_admin_cannot_get_misc_email_settings(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/email/settings')
            ->assertStatus(403);
    }

    public function test_admin_can_get_misc_email_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/email/settings');

        $response->assertStatus(200);
    }
}
