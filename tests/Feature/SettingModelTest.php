<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_and_get_a_setting(): void
    {
        Setting::set('test_key', 'test_value');
        $this->assertEquals('test_value', Setting::get('test_key'));
    }

    public function test_get_returns_default_when_not_set(): void
    {
        $result = Setting::get('nonexistent_key', 'default_fallback');
        $this->assertEquals('default_fallback', $result);
    }

    public function test_get_returns_null_default_when_not_set_and_no_default(): void
    {
        $result = Setting::get('nonexistent_key');
        $this->assertNull($result);
    }

    public function test_set_overwrites_existing_value(): void
    {
        Setting::set('test_key', 'first_value');
        Setting::set('test_key', 'second_value');
        $this->assertEquals('second_value', Setting::get('test_key'));
    }

    public function test_set_persists_to_database(): void
    {
        Setting::set('db_test_key', 'db_value');
        $this->assertDatabaseHas('settings', ['key' => 'db_test_key', 'value' => 'db_value']);
    }

    public function test_multiple_settings_can_coexist(): void
    {
        Setting::set('setting_a', 'value_a');
        Setting::set('setting_b', 'value_b');

        $this->assertEquals('value_a', Setting::get('setting_a'));
        $this->assertEquals('value_b', Setting::get('setting_b'));
    }

    public function test_set_stores_boolean_like_strings(): void
    {
        Setting::set('feature_flag', 'true');
        $this->assertEquals('true', Setting::get('feature_flag'));

        Setting::set('feature_flag', 'false');
        $this->assertEquals('false', Setting::get('feature_flag'));
    }
}
