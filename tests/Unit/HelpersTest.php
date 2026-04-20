<?php

namespace Tests\Unit;

use Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_module_enabled_returns_true_when_enabled(): void
    {
        config(['modules.stripe' => true]);
        $this->assertTrue(module_enabled('stripe'));
    }

    public function test_module_enabled_returns_false_when_disabled(): void
    {
        config(['modules.stripe' => false]);
        $this->assertFalse(module_enabled('stripe'));
    }

    public function test_module_enabled_returns_false_for_unknown_module(): void
    {
        $this->assertFalse(module_enabled('nonexistent'));
    }

    public function test_module_enabled_resolves_realtime_aliases_through_registry(): void
    {
        config([
            'modules.realtime' => true,
            'modules.broadcasting' => false,
        ]);

        $this->assertTrue(module_enabled('realtime'));
        $this->assertTrue(module_enabled('websocket'));
    }

    public function test_module_routes_does_nothing_when_module_disabled(): void
    {
        config(['modules.test_module' => false]);
        // Should not throw, just silently return
        module_routes('test_module', 'test.php');
        $this->assertTrue(true);
    }

    public function test_module_routes_does_nothing_when_file_missing(): void
    {
        config(['modules.test_module' => true]);
        // File doesn't exist, should silently return
        module_routes('test_module', 'nonexistent_route_file.php');
        $this->assertTrue(true);
    }
}
