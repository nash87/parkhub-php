<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminUpdatesRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_update_history_route_is_registered(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/updates/history')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_admin_update_check_uses_update_controller_contract(): void
    {
        Http::fake([
            'api.github.com/repos/nash87/parkhub-php/releases/latest' => Http::response([
                'tag_name' => 'v9.9.9',
                'html_url' => 'https://github.com/nash87/parkhub-php/releases/tag/v9.9.9',
                'body' => 'release notes',
                'published_at' => '2026-05-04T00:00:00Z',
            ], 200),
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/updates/check')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.latest_version', '9.9.9')
            ->assertJsonPath('data.release_url', 'https://github.com/nash87/parkhub-php/releases/tag/v9.9.9');
    }

    public function test_non_admin_cannot_apply_updates(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->postJson('/api/v1/admin/updates/apply', ['version' => '9.9.9'])
            ->assertForbidden();
    }
}
