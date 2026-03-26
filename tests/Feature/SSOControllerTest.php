<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SSOControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return $admin->createToken('test')->plainTextToken;
    }

    private function userToken(): string
    {
        $user = User::factory()->create(['role' => 'user']);

        return $user->createToken('test')->plainTextToken;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['modules.sso' => true]);

        // Clean up any existing provider file
        $path = storage_path('app/sso_providers.json');
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function test_providers_returns_empty_list_initially(): void
    {
        $response = $this->getJson('/api/v1/auth/sso/providers');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['providers' => []]]);
    }

    public function test_admin_can_create_sso_provider(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->putJson('/api/v1/admin/sso/okta', [
                'display_name' => 'Okta',
                'entity_id' => 'https://okta.example.com',
                'sso_url' => 'https://okta.example.com/sso',
                'certificate' => 'MIIC...',
                'metadata_url' => 'https://okta.example.com/metadata',
                'enabled' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.slug', 'okta')
            ->assertJsonPath('data.display_name', 'Okta');
    }

    public function test_admin_can_update_sso_provider(): void
    {
        // Create first
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->putJson('/api/v1/admin/sso/okta', [
                'display_name' => 'Okta',
                'entity_id' => 'https://okta.example.com',
                'sso_url' => 'https://okta.example.com/sso',
                'certificate' => 'MIIC...',
            ]);

        // Update
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->putJson('/api/v1/admin/sso/okta', [
                'display_name' => 'Okta SSO',
                'entity_id' => 'https://okta.example.com/v2',
                'sso_url' => 'https://okta.example.com/sso/v2',
                'certificate' => 'MIIC-updated...',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.display_name', 'Okta SSO');
    }

    public function test_admin_can_delete_sso_provider(): void
    {
        // Create
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->putJson('/api/v1/admin/sso/test-idp', [
                'display_name' => 'Test IdP',
                'entity_id' => 'https://test.example.com',
                'sso_url' => 'https://test.example.com/sso',
                'certificate' => 'MIIC...',
            ]);

        // Delete
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->deleteJson('/api/v1/admin/sso/test-idp');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_delete_nonexistent_provider_returns_404(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->deleteJson('/api/v1/admin/sso/nonexistent');

        $response->assertStatus(404);
    }

    public function test_login_returns_redirect_url_for_enabled_provider(): void
    {
        // Create an enabled provider
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->putJson('/api/v1/admin/sso/okta', [
                'display_name' => 'Okta',
                'entity_id' => 'https://okta.example.com',
                'sso_url' => 'https://okta.example.com/sso',
                'certificate' => 'MIIC...',
                'enabled' => true,
            ]);

        $response = $this->getJson('/api/v1/auth/sso/okta/login');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['redirect_url']);
    }

    public function test_login_returns_404_for_unknown_provider(): void
    {
        $response = $this->getJson('/api/v1/auth/sso/unknown/login');

        $response->assertStatus(404);
    }

    public function test_callback_requires_saml_response(): void
    {
        // Create provider
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->putJson('/api/v1/admin/sso/okta', [
                'display_name' => 'Okta',
                'entity_id' => 'https://okta.example.com',
                'sso_url' => 'https://okta.example.com/sso',
                'certificate' => 'MIIC...',
            ]);

        $response = $this->postJson('/api/v1/auth/sso/okta/callback');

        $response->assertStatus(400)
            ->assertJsonPath('error.message', 'Missing SAMLResponse');
    }

    public function test_upsert_validates_required_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->putJson('/api/v1/admin/sso/test', []);

        $response->assertStatus(422);
    }

    public function test_callback_is_inaccessible_when_sso_module_disabled(): void
    {
        config(['modules.sso' => false]);

        $response = $this->postJson('/api/v1/auth/sso/okta/callback', [
            'SAMLResponse' => base64_encode('<samlp:Response/>'),
        ]);

        $response->assertStatus(404);
    }

    public function test_providers_only_returns_enabled(): void
    {
        $token = $this->adminToken();

        // Create enabled
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/sso/enabled-idp', [
                'display_name' => 'Enabled',
                'entity_id' => 'https://enabled.example.com',
                'sso_url' => 'https://enabled.example.com/sso',
                'certificate' => 'MIIC...',
                'enabled' => true,
            ]);

        // Create disabled
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/sso/disabled-idp', [
                'display_name' => 'Disabled',
                'entity_id' => 'https://disabled.example.com',
                'sso_url' => 'https://disabled.example.com/sso',
                'certificate' => 'MIIC...',
                'enabled' => false,
            ]);

        $response = $this->getJson('/api/v1/auth/sso/providers');

        $response->assertStatus(200);
        $providers = $response->json('data.providers');
        $this->assertCount(1, $providers);
        $this->assertEquals('Enabled', $providers[0]['display_name']);
    }
}
