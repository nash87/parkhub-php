<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
     * Handle SAML callback (ACS endpoint).
     *
     * DISABLED: This endpoint previously decoded the SAMLResponse without
     * verifying XML signatures or assertion integrity, which allows an
     * attacker to forge arbitrary SAML assertions and authenticate as any
     * user. The callback is rejected until a proper SAML library
     * (onelogin/php-saml or similar) is integrated to perform:
     *
     *   1. XML signature verification against the IdP certificate
     *   2. Assertion condition validation (NotBefore, NotOnOrAfter, Audience)
     *   3. Replay protection (InResponseTo, one-time assertion ID tracking)
     *   4. Destination and recipient validation
     *
     * To re-enable: install onelogin/php-saml, implement full signature
     * verification in this method, and remove the 501 guard below.
     */
    public function callback(Request $request, string $provider): JsonResponse
    {
        Log::error('SAML callback rejected: no signature verification library installed', [
            'provider' => $provider,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => false,
            'error' => [
                'message' => 'SAML SSO callback is disabled. '
                    .'Server lacks a SAML library capable of XML signature verification. '
                    .'Install onelogin/php-saml and implement full assertion validation to enable this endpoint.',
            ],
        ], 501);
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

    // extractEmailFromSaml() removed — the simplified regex-based SAML
    // parser was unsafe (no signature verification). Re-implement with
    // onelogin/php-saml when enabling the callback endpoint.
}
