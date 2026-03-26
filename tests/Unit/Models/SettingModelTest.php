<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_and_get_value(): void
    {
        Setting::set('test_key', 'test_value');
        $this->assertEquals('test_value', Setting::get('test_key'));
    }

    public function test_get_returns_default_when_not_set(): void
    {
        $this->assertEquals('fallback', Setting::get('nonexistent_key', 'fallback'));
    }

    public function test_set_overwrites_existing_value(): void
    {
        Setting::set('overwrite_key', 'old');
        Setting::set('overwrite_key', 'new');
        $this->assertEquals('new', Setting::get('overwrite_key'));
    }

    public function test_fillable_attributes(): void
    {
        $setting = new Setting;
        $this->assertContains('key', $setting->getFillable());
        $this->assertContains('value', $setting->getFillable());
    }

    public function test_get_returns_null_default(): void
    {
        $this->assertNull(Setting::get('missing_key'));
    }

    public function test_preload_warms_cache_for_multiple_keys(): void
    {
        Setting::set('preload_a', 'alpha');
        Setting::set('preload_b', 'beta');
        Setting::set('preload_c', 'gamma');

        // Clear the individual caches populated by set() so preload must fetch from DB
        Cache::forget('setting:preload_a');
        Cache::forget('setting:preload_b');
        Cache::forget('setting:preload_c');

        Setting::preload(['preload_a', 'preload_b', 'preload_c']);

        $this->assertEquals('alpha', Setting::get('preload_a'));
        $this->assertEquals('beta', Setting::get('preload_b'));
        $this->assertEquals('gamma', Setting::get('preload_c'));
    }

    public function test_preload_does_not_cache_missing_keys(): void
    {
        // Missing keys must not be placed in cache so that Setting::get()
        // can still return caller-supplied defaults correctly.
        Setting::preload(['nonexistent_preload_key']);

        $this->assertFalse(Cache::has('setting:nonexistent_preload_key'));
        $this->assertEquals('my_default', Setting::get('nonexistent_preload_key', 'my_default'));
    }

    public function test_preload_skips_already_cached_keys(): void
    {
        Setting::set('cached_key', 'cached_value');
        // Warm cache via a normal get so the value is in cache
        Setting::get('cached_key');

        // A subsequent preload that includes this key should not disturb the cached value
        Setting::preload(['cached_key']);

        $this->assertEquals('cached_value', Setting::get('cached_key'));
    }

    public function test_preload_with_empty_array_does_nothing(): void
    {
        Setting::preload([]);
        // No exception thrown and no side effects
        $this->assertTrue(true);
    }

    public function test_preload_partial_hit(): void
    {
        Setting::set('partial_a', 'value_a');
        // Only partial_a exists in DB; partial_b does not

        Cache::forget('setting:partial_a');

        Setting::preload(['partial_a', 'partial_b']);

        $this->assertEquals('value_a', Setting::get('partial_a'));
        // partial_b is not in cache so get() should return the supplied default
        $this->assertEquals('fallback_b', Setting::get('partial_b', 'fallback_b'));
    }
}
