<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormRequestExtendedTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    // ── LoginRequest ────────────────────────────────────────────────────

    public function test_login_requires_username(): void
    {
        $this->postJson('/api/v1/auth/login', ['password' => 'test'])
            ->assertStatus(422);
    }

    public function test_login_requires_password(): void
    {
        $this->postJson('/api/v1/auth/login', ['username' => 'test'])
            ->assertStatus(422);
    }

    public function test_login_rejects_empty_strings(): void
    {
        $this->postJson('/api/v1/auth/login', ['username' => '', 'password' => ''])
            ->assertStatus(422);
    }

    // ── RegisterRequest ─────────────────────────────────────────────────

    public function test_register_allows_missing_username(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'test@example.com',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
            'name' => 'Test',
        ])->assertStatus(201);
    }

    public function test_register_requires_email(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    public function test_register_requires_name(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
        ])->assertStatus(422);
    }

    public function test_register_requires_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'ValidPass1',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    public function test_register_rejects_mismatched_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'ValidPass1',
            'password_confirmation' => 'DifferentPass1',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    public function test_register_username_min_3_chars(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'username' => 'ab',
            'email' => 'test@example.com',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    public function test_register_username_max_50_chars(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'username' => str_repeat('x', 51),
            'email' => 'test@example.com',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    public function test_register_username_alpha_dash_only(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'username' => 'user with spaces',
            'email' => 'test@example.com',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    public function test_register_rejects_invalid_email(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'email' => 'not-an-email',
            'password' => 'ValidPass1',
            'password_confirmation' => 'ValidPass1',
            'name' => 'Test',
        ])->assertStatus(422);
    }

    // ── ChangePasswordRequest ───────────────────────────────────────────

    public function test_change_password_requires_new_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPass123')]);

        $this->withHeaders($this->authHeader($user))
            ->patchJson('/api/v1/users/me/password', [
                'current_password' => 'OldPass123',
            ])
            ->assertStatus(422);
    }

    // ── ResetPasswordRequest ────────────────────────────────────────────

    public function test_reset_password_requires_email(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'sometoken',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertStatus(422);
    }

    public function test_reset_password_requires_token(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'test@example.com',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertStatus(422);
    }

    public function test_reset_password_requires_password(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'test@example.com',
            'token' => 'sometoken',
        ])->assertStatus(422);
    }

    public function test_reset_password_requires_confirmation(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'test@example.com',
            'token' => 'sometoken',
            'password' => 'NewPass123',
        ])->assertStatus(422);
    }

    // ── StoreBookingRequest ─────────────────────────────────────────────

    public function test_booking_requires_lot_id_v1(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/bookings', [
                'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(422);
    }

    public function test_booking_requires_start_time_v1(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test Lot', 'total_slots' => 5]);

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
            ])
            ->assertStatus(422);
    }

    public function test_booking_rejects_end_before_start(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test Lot', 'total_slots' => 5]);

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'end_time' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(422);
    }

    public function test_booking_notes_max_length(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test Lot', 'total_slots' => 5]);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'N1', 'status' => 'available']);

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
                'notes' => str_repeat('X', 2001),
            ])
            ->assertStatus(422);
    }

    // ── StoreVehicleRequest ─────────────────────────────────────────────

    public function test_vehicle_requires_plate_v1(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/vehicles', ['make' => 'BMW'])
            ->assertStatus(422);
    }

    public function test_vehicle_plate_max_20_chars(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/vehicles', ['plate' => str_repeat('X', 21)])
            ->assertStatus(422);
    }

    public function test_vehicle_make_max_100_chars(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/vehicles', [
                'plate' => 'ABC-123',
                'make' => str_repeat('X', 101),
            ])
            ->assertStatus(422);
    }

    public function test_vehicle_color_max_50_chars(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/vehicles', [
                'plate' => 'ABC-123',
                'color' => str_repeat('X', 51),
            ])
            ->assertStatus(422);
    }

    public function test_vehicle_create_with_all_fields(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/vehicles', [
                'plate' => 'DE-AB-1234',
                'make' => 'Tesla',
                'model' => 'Model 3',
                'color' => 'white',
                'is_default' => true,
            ])
            ->assertStatus(201);
    }

    // ── UpdateVehicleRequest ────────────────────────────────────────────

    public function test_vehicle_update_plate(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'OLD-123',
            'make' => 'BMW',
        ]);

        $this->withHeaders($this->authHeader($user))
            ->putJson("/api/v1/vehicles/{$vehicle->id}", [
                'plate' => 'NEW-456',
            ])
            ->assertStatus(200);

        $this->assertEquals('NEW-456', $vehicle->fresh()->plate);
    }

    public function test_vehicle_update_rejects_long_plate(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'OLD-123',
        ]);

        $this->withHeaders($this->authHeader($user))
            ->putJson("/api/v1/vehicles/{$vehicle->id}", [
                'plate' => str_repeat('X', 21),
            ])
            ->assertStatus(422);
    }

    // ── ImportUsersRequest ──────────────────────────────────────────────

    public function test_import_users_requires_users_array(): void
    {
        $response = $this->withHeaders($this->authHeader(User::factory()->admin()->create()))
            ->postJson('/api/v1/admin/users/import', []);

        $response->assertStatus(422);
    }

    public function test_import_users_validates_email(): void
    {
        $response = $this->withHeaders($this->authHeader(User::factory()->admin()->create()))
            ->postJson('/api/v1/admin/users/import', [
                'users' => [
                    ['username' => 'valid', 'email' => 'not-valid', 'name' => 'Test'],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_import_users_validates_username_min(): void
    {
        $response = $this->withHeaders($this->authHeader(User::factory()->admin()->create()))
            ->postJson('/api/v1/admin/users/import', [
                'users' => [
                    ['username' => 'ab', 'email' => 'test@example.com', 'name' => 'Test'],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_import_users_validates_role(): void
    {
        $response = $this->withHeaders($this->authHeader(User::factory()->admin()->create()))
            ->postJson('/api/v1/admin/users/import', [
                'users' => [
                    ['username' => 'validuser', 'email' => 'test@example.com', 'role' => 'superadmin'],
                ],
            ]);

        $response->assertStatus(422);
    }

    // ── UpdateSettingsRequest ────────────────────────────────────────────

    public function test_settings_rejects_invalid_license_plate_mode(): void
    {
        $response = $this->withHeaders($this->authHeader(User::factory()->admin()->create()))
            ->putJson('/api/v1/admin/settings', [
                'license_plate_mode' => 'invalid_mode',
            ]);

        $response->assertStatus(422);
    }

    public function test_settings_rejects_max_bookings_over_50(): void
    {
        $response = $this->withHeaders($this->authHeader(User::factory()->admin()->create()))
            ->putJson('/api/v1/admin/settings', [
                'max_bookings_per_day' => 100,
            ]);

        $response->assertStatus(422);
    }

    public function test_settings_accepts_boolean_as_string(): void
    {
        $response = $this->withHeaders($this->authHeader(User::factory()->admin()->create()))
            ->putJson('/api/v1/admin/settings', [
                'self_registration' => 'true',
            ]);

        $response->assertStatus(200);
    }

    public function test_settings_rejects_invalid_display_name_format(): void
    {
        $response = $this->withHeaders($this->authHeader(User::factory()->admin()->create()))
            ->putJson('/api/v1/admin/settings', [
                'display_name_format' => 'invalid',
            ]);

        $response->assertStatus(422);
    }

    // ── UpdateUserRequest ───────────────────────────────────────────────

    public function test_admin_update_user_rejects_invalid_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/v1/admin/users/{$user->id}", [
                'role' => 'god',
            ])
            ->assertStatus(422);
    }

    public function test_admin_update_user_rejects_invalid_email(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/v1/admin/users/{$user->id}", [
                'email' => 'not-an-email',
            ])
            ->assertStatus(422);
    }

    public function test_admin_update_user_rejects_short_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/v1/admin/users/{$user->id}", [
                'password' => 'short',
            ])
            ->assertStatus(422);
    }
}
