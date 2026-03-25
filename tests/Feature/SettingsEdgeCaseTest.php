<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        return [$admin, $token];
    }

    public function test_invalid_use_case_value_is_rejected(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'use_case' => 'invalid_value',
            ]);

        $response->assertStatus(422);
    }

    public function test_valid_use_case_values_accepted(): void
    {
        [$admin, $token] = $this->createAdmin();

        foreach (['corporate', 'university', 'residential', 'other'] as $useCase) {
            $response = $this->withHeader('Authorization', 'Bearer '.$token)
                ->putJson('/api/admin/settings', [
                    'use_case' => $useCase,
                ]);

            $response->assertStatus(200);
        }
    }

    public function test_empty_company_name_is_rejected(): void
    {
        [$admin, $token] = $this->createAdmin();

        // Laravel's 'string' rule rejects empty strings
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'company_name' => '',
            ]);

        $response->assertStatus(422);
    }

    public function test_company_name_too_long_is_rejected(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'company_name' => str_repeat('A', 256),
            ]);

        $response->assertStatus(422);
    }

    public function test_max_bookings_per_day_boundary_zero(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'max_bookings_per_day' => 0,
            ]);

        $response->assertStatus(200);
        $this->assertEquals('0', Setting::get('max_bookings_per_day'));
    }

    public function test_max_bookings_per_day_boundary_max(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'max_bookings_per_day' => 50,
            ]);

        $response->assertStatus(200);
        $this->assertEquals('50', Setting::get('max_bookings_per_day'));
    }

    public function test_max_bookings_per_day_exceeds_limit(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'max_bookings_per_day' => 51,
            ]);

        $response->assertStatus(422);
    }

    public function test_max_bookings_per_day_negative_rejected(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'max_bookings_per_day' => -1,
            ]);

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_update_settings(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'company_name' => 'Hacked',
            ])
            ->assertStatus(403);
    }

    public function test_boolean_settings_accept_string_booleans(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'self_registration' => 'true',
                'waitlist_enabled' => 'false',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('true', Setting::get('self_registration'));
        $this->assertEquals('false', Setting::get('waitlist_enabled'));
    }

    public function test_invalid_license_plate_mode_rejected(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'license_plate_mode' => 'not_a_valid_mode',
            ]);

        $response->assertStatus(422);
    }

    public function test_invalid_primary_color_rejected(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'primary_color' => 'not-a-color',
            ]);

        $response->assertStatus(422);
    }

    public function test_valid_hex_color_accepted(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'primary_color' => '#ff5500',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('#ff5500', Setting::get('primary_color'));
    }

    public function test_auto_release_minutes_boundary(): void
    {
        [$admin, $token] = $this->createAdmin();

        // Max boundary: 480 minutes
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', ['auto_release_minutes' => 480])
            ->assertStatus(200);

        // Over max
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', ['auto_release_minutes' => 481])
            ->assertStatus(422);
    }

    public function test_credits_per_booking_boundaries(): void
    {
        [$admin, $token] = $this->createAdmin();

        // Min valid
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', ['credits_per_booking' => 1])
            ->assertStatus(200);

        // Max valid
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', ['credits_per_booking' => 100])
            ->assertStatus(200);

        // Over max
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', ['credits_per_booking' => 101])
            ->assertStatus(422);
    }

    public function test_unknown_settings_key_ignored(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/admin/settings', [
                'company_name' => 'ValidCo',
                'malicious_key' => 'evil_value',
            ]);

        $response->assertStatus(200);
        $this->assertNull(Setting::get('malicious_key'));
        $this->assertEquals('ValidCo', Setting::get('company_name'));
    }

    public function test_import_backup_strips_unknown_keys(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/restore', [
                'settings' => [
                    'company_name' => 'RestoredCo',
                    'arbitrary_key' => 'should_be_ignored',
                    '__proto__' => 'polluted',
                ],
            ]);

        $response->assertStatus(200);
        $this->assertEquals('RestoredCo', Setting::get('company_name'));
        $this->assertNull(Setting::get('arbitrary_key'));
        $this->assertNull(Setting::get('__proto__'));
    }

    public function test_import_backup_count_reflects_only_allowed_keys(): void
    {
        [$admin, $token] = $this->createAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/restore', [
                'settings' => [
                    'company_name' => 'CountCo',
                    'primary_color' => '#aabbcc',
                    'unknown_key' => 'ignored',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['data' => ['settings_imported' => 2]]);
    }

    public function test_import_backup_allowed_keys_are_persisted(): void
    {
        [$admin, $token] = $this->createAdmin();

        $payload = [
            'company_name' => 'BackupCo',
            'self_registration' => 'false',
            'max_bookings_per_day' => '5',
            'credits_enabled' => 'true',
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/restore', ['settings' => $payload])
            ->assertStatus(200);

        $this->assertEquals('BackupCo', Setting::get('company_name'));
        $this->assertEquals('false', Setting::get('self_registration'));
        $this->assertEquals('5', Setting::get('max_bookings_per_day'));
        $this->assertEquals('true', Setting::get('credits_enabled'));
    }

    public function test_non_admin_cannot_import_backup(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/restore', [
                'settings' => ['company_name' => 'Hacked'],
            ])
            ->assertStatus(403);
    }
}
