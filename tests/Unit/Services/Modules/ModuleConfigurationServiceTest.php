<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Modules;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\ModuleRegistry;
use App\Services\Modules\ModuleConfigurationService;
use App\Services\Modules\ModuleConfigurationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleConfigurationServiceTest extends TestCase
{
    use RefreshDatabase;

    // 'widgets' is both runtime-toggleable AND ships a config_schema.
    private const TOGGLEABLE_MODULE = 'widgets';

    private function service(): ModuleConfigurationService
    {
        return app(ModuleConfigurationService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function actor(?User $user = null): array
    {
        $user ??= User::factory()->create(['role' => 'admin']);

        return [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip_address' => '203.0.113.7',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function widgetsSchema(): array
    {
        $schema = ModuleRegistry::configSchema(self::TOGGLEABLE_MODULE);
        $this->assertNotNull($schema, "'widgets' module lost its config_schema — fixture drift.");

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function recommendationWeightedValues(): array
    {
        return [
            'algorithm' => 'weighted_v1',
            'allocation_strategy' => 'weighted_v1',
            'exact_cover_max_options' => 256,
            'exact_cover_max_search_nodes' => 10000,
            'weight_frequency' => 40,
            'weight_preferred_lot' => 20,
            'weight_availability' => 30,
            'weight_price' => 20,
            'weight_distance' => 10,
            'weight_accessibility_bonus' => 0,
            'weight_feature_bonus' => 2,
            'max_results' => 5,
            'explain' => true,
            'profile_safe_mode' => true,
        ];
    }

    public function test_toggle_runtime_state_unknown_module_returns_not_found_without_writing(): void
    {
        $result = $this->service()->toggleRuntimeState('does-not-exist', true, $this->actor());

        $this->assertSame(ModuleConfigurationStatus::NotFound, $result->status);
        $this->assertSame('does-not-exist', $result->moduleName);
        $this->assertDatabaseCount('audit_log', 0);
    }

    public function test_toggle_runtime_state_non_toggleable_module_returns_conflict(): void
    {
        // 'bookings' is NOT in the runtime-toggleable allowlist.
        $result = $this->service()->toggleRuntimeState('bookings', false, $this->actor());

        $this->assertSame(ModuleConfigurationStatus::NotToggleable, $result->status);
        $this->assertDatabaseCount('audit_log', 0);
    }

    public function test_toggle_runtime_state_persists_setting_and_writes_audit_log(): void
    {
        $actor = $this->actor();

        $result = $this->service()->toggleRuntimeState(self::TOGGLEABLE_MODULE, false, $actor);

        $this->assertTrue($result->isOk());
        $this->assertSame(
            '0',
            Setting::get(ModuleRegistry::runtimeSettingKey(self::TOGGLEABLE_MODULE)),
        );

        $audit = AuditLog::query()->where('action', 'module_runtime_toggled')->first();
        $this->assertNotNull($audit);
        $this->assertSame($actor['user_id'], $audit->user_id);
        $this->assertSame(self::TOGGLEABLE_MODULE, $audit->target_id);
        $this->assertSame('module', $audit->target_type);
        $this->assertFalse($audit->details['new_state']);
    }

    public function test_update_config_schema_violation_returns_formatted_details(): void
    {
        $result = $this->service()->updateConfig(
            self::TOGGLEABLE_MODULE,
            $this->widgetsSchema(),
            ['max_widgets_per_dashboard' => 'not-an-int'],
            $this->actor(),
        );

        $this->assertSame(ModuleConfigurationStatus::ValidationFailed, $result->status);
        $this->assertIsArray($result->details);
        $this->assertNotEmpty($result->details);
        $this->assertSame(0, AuditLog::query()->where('action', 'module_config_updated')->count());
    }

    public function test_recommendations_weighted_config_does_not_require_pipeline_settings(): void
    {
        $schema = ModuleRegistry::configSchema('recommendations');
        $this->assertNotNull($schema, "'recommendations' module lost its config_schema — fixture drift.");

        $result = $this->service()->updateConfig(
            'recommendations',
            $schema,
            $this->recommendationWeightedValues(),
            $this->actor(),
        );

        $this->assertTrue($result->isOk());
        $this->assertSame(
            '"weighted_v1"',
            Setting::get(ModuleRegistry::configSettingKey('recommendations', 'algorithm')),
        );
    }

    public function test_recommendations_fop_pipeline_config_requires_pipeline_settings(): void
    {
        $schema = ModuleRegistry::configSchema('recommendations');
        $this->assertNotNull($schema, "'recommendations' module lost its config_schema — fixture drift.");

        $values = $this->recommendationWeightedValues();
        $values['algorithm'] = 'fop_pipeline_v1';

        $result = $this->service()->updateConfig(
            'recommendations',
            $schema,
            $values,
            $this->actor(),
        );

        $this->assertSame(ModuleConfigurationStatus::ValidationFailed, $result->status);
        $this->assertIsArray($result->details);
        $this->assertNotEmpty($result->details);
    }

    public function test_update_config_persists_each_key_and_writes_audit_log(): void
    {
        $actor = $this->actor();

        $result = $this->service()->updateConfig(
            self::TOGGLEABLE_MODULE,
            $this->widgetsSchema(),
            ['max_widgets_per_dashboard' => 12],
            $actor,
        );

        $this->assertTrue($result->isOk());

        $raw = Setting::get(
            ModuleRegistry::configSettingKey(self::TOGGLEABLE_MODULE, 'max_widgets_per_dashboard'),
        );
        $this->assertSame(12, json_decode((string) $raw, true));

        $audit = AuditLog::query()->where('action', 'module_config_updated')->first();
        $this->assertNotNull($audit);
        $this->assertSame(['max_widgets_per_dashboard'], $audit->details['keys_changed']);
        $this->assertSame(self::TOGGLEABLE_MODULE, $audit->target_id);
    }

    public function test_read_persisted_values_returns_empty_for_module_without_schema(): void
    {
        $values = $this->service()->readPersistedValues('zones');

        $this->assertSame([], $values);
    }

    public function test_read_persisted_values_round_trips_stored_values(): void
    {
        Setting::set(
            ModuleRegistry::configSettingKey(self::TOGGLEABLE_MODULE, 'max_widgets_per_dashboard'),
            json_encode(7),
        );

        $values = $this->service()->readPersistedValues(self::TOGGLEABLE_MODULE);

        $this->assertSame(['max_widgets_per_dashboard' => 7], $values);
    }
}
