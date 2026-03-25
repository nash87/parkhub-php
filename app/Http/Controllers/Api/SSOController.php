<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * SAML/SSO Enterprise Authentication controller.
 *
 * Public endpoints:
 *   GET  /api/v1/auth/sso/providers            — list enabled SSO providers
 *   GET  /api/v1/auth/sso/{provider}/login      — initiate SSO login (returns redirect URL)
 *   POST /api/v1/auth/sso/{provider}/callback    — handle SAML callback (ACS endpoint)
 *
 * Admin endpoints:
 *   PUT    /api/v1/admin/sso/{provider}          — create or update SSO provider
 *   DELETE /api/v1/admin/sso/{provider}          — remove SSO provider
 */
class SSOController extends Controller
{
    /**
     * List all configured and enabled SSO providers (public — no secrets exposed).
     */
    public function providers(): JsonResponse
    {
        $providers = collect($this->loadProviders())
            ->filter(fn (array $p) => $p['enabled'] ?? false)
            ->map(fn (array $p) => [
                'slug' => $p['slug'],
                'display_name' => $p['display_name'],
                'enabled' => true,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => ['providers' => $providers],
        ]);
    }

    /**
     * Initiate SSO login — returns redirect URL for the identity provider.
     */
    public function login(string $provider): JsonResponse
    {
        $config = $this->findProvider($provider);

        if (! $config || ! ($config['enabled'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => ['message' => "SSO provider '{$provider}' not found or disabled"],
            ], 404);
        }

        // Build SAML AuthnRequest redirect URL
        $samlId = '_'.Str::random(32);
        $issuer = rtrim(config('app.url'), '/');
        $acsUrl = "{$issuer}/api/v1/auth/sso/{$provider}/callback";

        $redirectUrl = $config['sso_url'].'?'.http_build_query([
            'SAMLRequest' => base64_encode("<samlp:AuthnRequest ID=\"{$samlId}\" IssueInstant=\"".now()->toIso8601String()."\" Destination=\"{$config['sso_url']}\" AssertionConsumerServiceURL=\"{$acsUrl}\"><saml:Issuer>{$issuer}</saml:Issuer></samlp:AuthnRequest>"),
            'RelayState' => $acsUrl,
        ]);

        return response()->json([
            'success' => true,
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * Handle SAML callback — validate assertion and create/login user.
     *
     * SECURITY WARNING: This implementation decodes the SAMLResponse without
     * verifying the XML signature or assertion integrity. It MUST NOT be used
     * in production without integrating onelogin/php-saml (or an equivalent
     * SAML library) for full XML signature and condition validation. The SSO
     * module defaults to disabled (MODULE_SSO=false) specifically because of
     * this limitation.
     */
    public function callback(Request $request, string $provider): JsonResponse
    {
        $config = $this->findProvider($provider);

        if (! $config) {
            return response()->json([
                'success' => false,
                'error' => ['message' => "SSO provider '{$provider}' not found"],
            ], 404);
        }

        $samlResponse = $request->input('SAMLResponse');
        if (! $samlResponse) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Missing SAMLResponse'],
            ], 400);
        }

        // Decode and parse SAML response.
        // SECURITY: No XML signature or assertion validation is performed here.
        // This is a simplified parser only. Production deployments MUST use
        // onelogin/php-saml for full signature verification before trusting any
        // data extracted from the SAMLResponse.
        $decoded = base64_decode($samlResponse);
        $email = $this->extractEmailFromSaml($decoded);

        if (! $email) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Could not extract email from SAML assertion'],
            ], 400);
        }

        // Find or create user
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['last_login' => now()]);
        } else {
            $username = Str::slug(explode('@', $email)[0], '_').'_'.Str::random(4);
            $user = User::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'name' => explode('@', $email)[0],
                'is_active' => true,
                'preferences' => ['language' => 'en', 'theme' => 'system', 'notifications_enabled' => true],
            ]);
            $user->role = 'user';
            $user->save();
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Account is disabled'],
            ], 403);
        }

        $token = $user->createToken('sso-'.$provider);

        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => 'sso_login',
            'details' => ['provider' => $provider],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token->plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $user->role,
                ],
            ],
        ]);
    }

    /**
     * Create or update an SSO provider (admin only).
     */
    public function upsert(Request $request, string $provider): JsonResponse
    {
        $request->validate([
            'display_name' => 'required|string|max:255',
            'entity_id' => 'required|string|max:1024',
            'sso_url' => 'required|url|max:2048',
            'certificate' => 'required|string',
            'metadata_url' => 'nullable|string|max:2048',
            'enabled' => 'boolean',
        ]);

        $providers = $this->loadProviders();

        $slug = Str::slug($provider);
        $existing = collect($providers)->firstWhere('slug', $slug);
        $isNew = ! $existing;

        $entry = [
            'slug' => $slug,
            'display_name' => $request->input('display_name'),
            'entity_id' => $request->input('entity_id'),
            'metadata_url' => $request->input('metadata_url', ''),
            'sso_url' => $request->input('sso_url'),
            'certificate' => $request->input('certificate'),
            'enabled' => $request->boolean('enabled', true),
            'created_at' => $existing['created_at'] ?? now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        // Replace or append
        $providers = collect($providers)->reject(fn ($p) => $p['slug'] === $slug)->values()->push($entry)->toArray();

        $this->saveProviders($providers);

        return response()->json([
            'success' => true,
            'data' => $entry,
        ], $isNew ? 201 : 200);
    }

    /**
     * Delete an SSO provider (admin only).
     */
    public function destroy(string $provider): JsonResponse
    {
        $providers = $this->loadProviders();
        $slug = Str::slug($provider);
        $filtered = collect($providers)->reject(fn ($p) => $p['slug'] === $slug)->values()->toArray();

        if (count($filtered) === count($providers)) {
            return response()->json([
                'success' => false,
                'error' => ['message' => "Provider '{$slug}' not found"],
            ], 404);
        }

        $this->saveProviders($filtered);

        return response()->json(['success' => true, 'message' => 'Provider deleted']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Load SSO providers from JSON storage.
     */
    private function loadProviders(): array
    {
        $path = storage_path('app/sso_providers.json');

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?: [];
    }

    /**
     * Save SSO providers to JSON storage.
     */
    private function saveProviders(array $providers): void
    {
        $path = storage_path('app/sso_providers.json');
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($providers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Find a provider by slug.
     */
    private function findProvider(string $slug): ?array
    {
        return collect($this->loadProviders())->firstWhere('slug', Str::slug($slug));
    }

    /**
     * Extract email from a decoded SAML response (simplified parser).
     */
    private function extractEmailFromSaml(string $xml): ?string
    {
        // Try to extract NameID or email attribute from SAML response
        if (preg_match('/<saml:NameID[^>]*>([^<]+)<\/saml:NameID>/i', $xml, $matches)) {
            $value = trim($matches[1]);
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        // Try AttributeStatement email
        if (preg_match('/emailAddress["\'][^>]*>.*?<saml:AttributeValue[^>]*>([^<]+)/si', $xml, $matches)) {
            $value = trim($matches[1]);
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return null;
    }
}
