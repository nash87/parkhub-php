<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the enriched `/api/v1/modules` envelope and the per-module
 * show endpoint. The Rust edition ships the same shape from
 * `parkhub-server`; keeping this test in lock-step with the Rust unit
 * tests in `parkhub-server/src/api/modules_meta.rs` is how we stop the
 * two backends from drifting.
 *
 * All payloads travel through the global ApiResponseWrapper envelope
 * (`{success, data, error, meta}`), so the backwards-compat fields
 * live at `data.modules` / `data.module_info`.
 */
class ModuleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_backward_compat_envelope(): void
    {
        $response = $this->getJson('/api/v1/modules');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);

        $this->assertArrayHasKey('modules', $data);
        $this->assertArrayHasKey('module_info', $data);
        $this->assertArrayHasKey('version', $data);

        // `modules` is the historic {name => bool} map — keep it that way.
        $this->assertIsArray($data['modules']);
        foreach ($data['modules'] as $name => $enabled) {
            $this->assertIsString($name, 'module map keys must be strings');
            $this->assertIsBool($enabled, "module '{$name}' must map to a bool");
        }

        $this->assertArrayHasKey('bookings', $data['modules']);
        $this->assertArrayHasKey('vehicles', $data['modules']);
    }

    public function test_index_includes_module_info_array(): void
    {
        $response = $this->getJson('/api/v1/modules');
        $response->assertOk();

        $moduleInfo = $response->json('data.module_info');
        $this->assertIsArray($moduleInfo);
        $this->assertNotEmpty($moduleInfo);

        // Every entry must carry the full ModuleInfo contract that
        // parkhub-web relies on to render the Modules Dashboard.
        foreach ($moduleInfo as $entry) {
            $this->assertIsArray($entry);
            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('category', $entry);
            $this->assertArrayHasKey('description', $entry);
            $this->assertArrayHasKey('enabled', $entry);
            $this->assertArrayHasKey('runtime_toggleable', $entry);
            $this->assertArrayHasKey('runtime_enabled', $entry);
            $this->assertArrayHasKey('config_keys', $entry);
            $this->assertArrayHasKey('ui_route', $entry);
            $this->assertArrayHasKey('depends_on', $entry);
            $this->assertArrayHasKey('version', $entry);
            // v3: every ModuleInfo must carry a `config_schema` slot
            // (null for env-only modules, JSON Schema object otherwise).
            $this->assertArrayHasKey('config_schema', $entry);
            $this->assertTrue(
                $entry['config_schema'] === null || is_array($entry['config_schema']),
                "config_schema of '{$entry['name']}' must be array|null",
            );

            $this->assertIsString($entry['name']);
            $this->assertIsString($entry['category']);
            $this->assertIsString($entry['description']);
            $this->assertIsBool($entry['enabled']);
            $this->assertIsBool($entry['runtime_toggleable']);
            $this->assertIsBool($entry['runtime_enabled']);
            $this->assertIsArray($entry['config_keys']);
            $this->assertIsArray($entry['depends_on']);
            $this->assertIsString($entry['version']);
            // ui_route is nullable string; both are acceptable.
            $this->assertTrue(
                $entry['ui_route'] === null || is_string($entry['ui_route']),
                "ui_route of '{$entry['name']}' must be string|null",
            );
            if (is_string($entry['ui_route'])) {
                $this->assertStringStartsWith('/', $entry['ui_route']);
            }
        }
    }

    public function test_index_uses_canonical_product_slugs_in_public_payloads(): void
    {
        $response = $this->getJson('/api/v1/modules');
        $response->assertOk();

        $modules = $response->json('data.modules');
        $this->assertIsArray($modules);
        $this->assertArrayHasKey('absence-approval', $modules);
        $this->assertArrayHasKey('dynamic-pricing', $modules);
        $this->assertArrayHasKey('multi-tenant', $modules);

        $this->assertArrayNotHasKey('absence_approval', $modules);
        $this->assertArrayNotHasKey('dynamic_pricing', $modules);
        $this->assertArrayNotHasKey('multi_tenant', $modules);

        $moduleNames = array_column($response->json('data.module_info'), 'name');
        $this->assertContains('absence-approval', $moduleNames);
        $this->assertContains('guest', $moduleNames);
        $this->assertContains('calendar', $moduleNames);
        $this->assertContains('export', $moduleNames);
        $this->assertContains('settings', $moduleNames);
        $this->assertContains('translations', $moduleNames);
        $this->assertContains('team', $moduleNames);
        $this->assertContains('pwa', $moduleNames);
        $this->assertContains('realtime', $moduleNames);
        $this->assertContains('push', $moduleNames);
        $this->assertContains('email', $moduleNames);
        $this->assertContains('email-templates', $moduleNames);
        $this->assertContains('calendar-drag', $moduleNames);
        $this->assertContains('api-versioning', $moduleNames);
        $this->assertContains('social', $moduleNames);

        $this->assertArrayHasKey('social', $modules);
        $this->assertFalse($modules['social']);
        $this->assertArrayHasKey('api-versioning', $modules);

        $this->assertNotContains('absence_approval', $moduleNames);
        $this->assertNotContains('data_export', $moduleNames);
        $this->assertNotContains('broadcasting', $moduleNames);
        $this->assertNotContains('push_notifications', $moduleNames);
        $this->assertNotContains('websocket', $moduleNames);
        $this->assertNotContains('web_push', $moduleNames);
        $this->assertNotContains('email_templates', $moduleNames);
        $this->assertNotContains('calendar_drag', $moduleNames);
        $this->assertNotContains('api_versioning', $moduleNames);
    }

    public function test_show_returns_known_module(): void
    {
        $response = $this->getJson('/api/v1/modules/bookings');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'bookings')
            ->assertJsonPath('data.category', 'Core')
            ->assertJsonPath('data.ui_route', '/bookings')
            // `bookings` stays env-only (data-loss risk) so it must
            // never appear on the runtime-toggleable allowlist.
            ->assertJsonPath('data.runtime_toggleable', false);

        $this->assertIsBool($response->json('data.enabled'));
    }

    public function test_show_accepts_canonical_slug_for_legacy_internal_module_name(): void
    {
        $response = $this->getJson('/api/v1/modules/multi-tenant');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'multi-tenant')
            ->assertJsonPath('data.category', 'Enterprise');
    }

    public function test_show_keeps_legacy_slug_compatible_but_emits_canonical_name(): void
    {
        $response = $this->getJson('/api/v1/modules/email_templates');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'email-templates')
            ->assertJsonPath('data.category', 'Notification');
    }

    public function test_show_maps_export_legacy_slug_to_canonical_name(): void
    {
        $response = $this->getJson('/api/v1/modules/data_export');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'export')
            ->assertJsonPath('data.category', 'Admin');
    }

    public function test_show_maps_api_versioning_legacy_slug_to_canonical_name(): void
    {
        $response = $this->getJson('/api/v1/modules/api_versioning');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'api-versioning')
            ->assertJsonPath('data.category', 'Integration');
    }

    public function test_show_maps_realtime_and_push_legacy_slugs_to_canonical_names(): void
    {
        $this->getJson('/api/v1/modules/broadcasting')
            ->assertOk()
            ->assertJsonPath('data.name', 'realtime')
            ->assertJsonPath('data.category', 'Integration');

        $this->getJson('/api/v1/modules/websocket')
            ->assertOk()
            ->assertJsonPath('data.name', 'realtime')
            ->assertJsonPath('data.category', 'Integration');

        $this->getJson('/api/v1/modules/push_notifications')
            ->assertOk()
            ->assertJsonPath('data.name', 'push')
            ->assertJsonPath('data.category', 'Notification');

        $this->getJson('/api/v1/modules/web_push')
            ->assertOk()
            ->assertJsonPath('data.name', 'push')
            ->assertJsonPath('data.category', 'Notification');
    }

    public function test_show_exposes_email_and_email_templates_as_notification_modules(): void
    {
        $this->getJson('/api/v1/modules/email')
            ->assertOk()
            ->assertJsonPath('data.name', 'email')
            ->assertJsonPath('data.category', 'Notification');

        $this->getJson('/api/v1/modules/email_templates')
            ->assertOk()
            ->assertJsonPath('data.name', 'email-templates')
            ->assertJsonPath('data.category', 'Notification')
            ->assertJsonPath('data.depends_on.0', 'email');
    }

    public function test_show_exposes_social_module_with_rust_parity_contract(): void
    {
        $this->getJson('/api/v1/modules/social')
            ->assertOk()
            ->assertJsonPath('data.name', 'social')
            ->assertJsonPath('data.category', 'Experimental')
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.runtime_toggleable', true);
    }

    public function test_show_exposes_calendar_drag_as_depending_on_calendar(): void
    {
        $this->getJson('/api/v1/modules/calendar-drag')
            ->assertOk()
            ->assertJsonPath('data.name', 'calendar-drag')
            ->assertJsonPath('data.category', 'Booking')
            ->assertJsonPath('data.depends_on.0', 'calendar');
    }

    public function test_show_returns_404_for_unknown_module(): void
    {
        $response = $this->getJson('/api/v1/modules/this-module-does-not-exist');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'MODULE_NOT_FOUND');
    }

    public function test_all_modules_belong_to_a_category(): void
    {
        $valid = ModuleRegistry::CATEGORIES;
        $this->assertNotEmpty($valid);

        foreach (ModuleRegistry::all() as $entry) {
            $this->assertContains(
                $entry['category'],
                $valid,
                "module '{$entry['name']}' has unknown category '{$entry['category']}'",
            );
        }
    }

    public function test_module_names_are_unique(): void
    {
        $seen = [];
        foreach (ModuleRegistry::all() as $entry) {
            $this->assertArrayNotHasKey(
                $entry['name'],
                $seen,
                "duplicate module name in registry: {$entry['name']}",
            );
            $seen[$entry['name']] = true;
        }

        // Sanity: the registry is supposed to mirror ~60+ modules the
        // Rust edition ships. Guard against a copy-paste accident that
        // silently truncates the table.
        $this->assertGreaterThanOrEqual(50, count($seen));
    }

    // ── v2: runtime toggle contract ─────────────────────────────────────

    public function test_patch_toggles_runtime_state_for_toggleable_module(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        // `map` is on the runtime-toggleable allowlist.
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/modules/map', ['runtime_enabled' => false]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'map')
            ->assertJsonPath('data.runtime_toggleable', true)
            ->assertJsonPath('data.runtime_enabled', false);

        // Setting row persisted — the next registry materialization
        // must honour it without hitting the env flag.
        $this->assertSame('0', Setting::get('module.map.runtime_enabled'));

        // Audit log entry written.
        $this->assertDatabaseHas('audit_log', [
            'action' => 'module_runtime_toggled',
            'target_type' => 'module',
            'target_id' => 'map',
        ]);
    }

    public function test_patch_returns_409_for_non_toggleable_module(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        // `bookings` is explicitly not on the runtime-toggleable allowlist.
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/modules/bookings', ['runtime_enabled' => false]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'MODULE_NOT_TOGGLEABLE');

        // No side effect on the settings table or audit log.
        $this->assertNull(Setting::get('module.bookings.runtime_enabled'));
        $this->assertDatabaseMissing('audit_log', [
            'action' => 'module_runtime_toggled',
            'target_id' => 'bookings',
        ]);
    }

    public function test_patch_returns_404_for_unknown_module(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/modules/does-not-exist', ['runtime_enabled' => true]);

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'MODULE_NOT_FOUND');
    }

    public function test_patch_requires_admin_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/modules/map', ['runtime_enabled' => false]);

        $response->assertStatus(403);

        // Regular user must not be able to mutate runtime state.
        $this->assertNull(Setting::get('module.map.runtime_enabled'));
    }

    public function test_module_gate_returns_404_when_disabled(): void
    {
        // Persist a runtime override that disables `map` even though
        // the env flag is on — the gate must honour the override.
        config(['modules.map' => true]);
        ModuleRegistry::setRuntimeEnabled('map', false);

        // ApiResponseWrapper re-shapes the raw middleware reply into
        // the standard envelope, hoisting `error` to `error.code`.
        $this->getJson('/api/v1/lots/map')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'MODULE_DISABLED');
    }

    public function test_registry_applies_setting_override(): void
    {
        // env says `map` is on…
        config(['modules.map' => true]);

        // …but the admin has flipped the runtime override off.
        ModuleRegistry::setRuntimeEnabled('map', false);

        $info = ModuleRegistry::get('map');
        $this->assertNotNull($info);
        $this->assertTrue($info['enabled'], 'env-derived `enabled` must still be true');
        $this->assertFalse($info['runtime_enabled'], 'override must win for runtime_enabled');
        $this->assertTrue($info['runtime_toggleable']);

        // Flipping the override back on resets the runtime state.
        ModuleRegistry::setRuntimeEnabled('map', true);
        $info = ModuleRegistry::get('map');
        $this->assertNotNull($info);
        $this->assertTrue($info['runtime_enabled']);

        // Non-toggleable modules must ignore setRuntimeEnabled calls so
        // a misuse in the controller layer can't bypass the allowlist.
        $before = Setting::get('module.bookings.runtime_enabled');
        ModuleRegistry::setRuntimeEnabled('bookings', false);
        $this->assertSame($before, Setting::get('module.bookings.runtime_enabled'));

        // And unused audit log entries don't leak. (Sanity.)
        $this->assertSame(0, AuditLog::where('target_id', 'bookings')->count());
    }

    public function test_registry_uses_legacy_setting_keys_behind_canonical_slugs(): void
    {
        $this->assertSame(
            'module.email_templates.config.from_address',
            ModuleRegistry::configSettingKey('email-templates', 'from_address'),
        );

        $this->assertSame(
            'module.calendar_drag.runtime_enabled',
            ModuleRegistry::runtimeSettingKey('calendar-drag'),
        );

        config(['modules.calendar_drag' => true]);
        ModuleRegistry::setRuntimeEnabled('calendar-drag', false);

        $this->assertSame('0', Setting::get('module.calendar_drag.runtime_enabled'));

        $info = ModuleRegistry::get('calendar-drag');
        $this->assertNotNull($info);
        $this->assertSame('calendar-drag', $info['name']);
        $this->assertFalse($info['runtime_enabled']);
    }

    public function test_registry_exposes_route_backed_modules_missing_from_legacy_php_table(): void
    {
        config([
            'modules.bookings' => true,
            'modules.data_export' => true,
            'modules.enhanced_pwa' => true,
        ]);

        $guest = ModuleRegistry::get('guest');
        $this->assertNotNull($guest);
        $this->assertSame('guest', $guest['name']);
        $this->assertTrue($guest['enabled']);

        $calendar = ModuleRegistry::get('calendar');
        $this->assertNotNull($calendar);
        $this->assertSame('/calendar', $calendar['ui_route']);
        $this->assertTrue($calendar['enabled']);
        $this->assertSame([], $calendar['depends_on']);

        $calendarDrag = ModuleRegistry::get('calendar-drag');
        $this->assertNotNull($calendarDrag);
        $this->assertSame('calendar-drag', $calendarDrag['name']);
        $this->assertContains('calendar', $calendarDrag['depends_on']);

        $export = ModuleRegistry::get('export');
        $this->assertNotNull($export);
        $this->assertSame('export', $export['name']);
        $this->assertTrue($export['enabled']);

        $pwa = ModuleRegistry::get('pwa');
        $this->assertNotNull($pwa);
        $this->assertSame('pwa', $pwa['name']);
        $this->assertTrue($pwa['enabled']);

        $enhancedPwa = ModuleRegistry::get('enhanced-pwa');
        $this->assertNotNull($enhancedPwa);
        $this->assertContains('pwa', $enhancedPwa['depends_on']);
    }

    public function test_registry_exposes_canonical_realtime_push_and_email_contract(): void
    {
        config([
            'modules.realtime' => true,
            'modules.push_notifications' => true,
        ]);

        $realtime = ModuleRegistry::get('realtime');
        $this->assertNotNull($realtime);
        $this->assertSame('realtime', $realtime['name']);
        $this->assertTrue($realtime['enabled']);

        $websocket = ModuleRegistry::get('websocket');
        $this->assertNotNull($websocket);
        $this->assertSame('realtime', $websocket['name']);
        $this->assertTrue($websocket['enabled']);

        $broadcasting = ModuleRegistry::get('broadcasting');
        $this->assertNotNull($broadcasting);
        $this->assertSame('realtime', $broadcasting['name']);
        $this->assertTrue($broadcasting['enabled']);

        $push = ModuleRegistry::get('push');
        $this->assertNotNull($push);
        $this->assertSame('push', $push['name']);
        $this->assertTrue($push['enabled']);

        $email = ModuleRegistry::get('email');
        $this->assertNotNull($email);
        $this->assertSame('email', $email['name']);
        $this->assertTrue($email['enabled']);

        $emailTemplates = ModuleRegistry::get('email-templates');
        $this->assertNotNull($emailTemplates);
        $this->assertContains('email', $emailTemplates['depends_on']);
    }

    public function test_registry_exposes_social_module_as_disabled_runtime_toggleable_contract(): void
    {
        config(['modules.social' => false]);

        $social = ModuleRegistry::get('social');
        $this->assertNotNull($social);
        $this->assertSame('social', $social['name']);
        $this->assertSame('Experimental', $social['category']);
        $this->assertFalse($social['enabled']);
        $this->assertTrue($social['runtime_toggleable']);
    }
}
