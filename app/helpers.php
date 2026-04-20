<?php

declare(strict_types=1);

use App\Services\ModuleRegistry;
use Illuminate\Support\Facades\Route;

if (! function_exists('module_enabled')) {
    /**
     * Check whether a module is enabled.
     */
    function module_enabled(string $module): bool
    {
        if (config()->has("modules.{$module}")) {
            return (bool) config("modules.{$module}", false);
        }

        $info = ModuleRegistry::get($module);
        if ($info !== null) {
            return (bool) $info['runtime_enabled'];
        }

        return (bool) config("modules.{$module}", false);
    }
}

if (! function_exists('module_routes')) {
    /**
     * Load a module route file only if the module is enabled.
     *
     * @param  string  $module  Module key from config/modules.php
     * @param  string  $file  Route file name (without path), e.g. 'bookings.php'
     * @param  array  $middleware  Additional middleware to wrap the routes
     */
    function module_routes(string $module, string $file, array $middleware = []): void
    {
        if (! module_enabled($module)) {
            return;
        }

        $path = base_path("routes/modules/{$file}");
        if (! file_exists($path)) {
            return;
        }

        if (! empty($middleware)) {
            Route::middleware($middleware)->group($path);
        } else {
            Route::group([], $path);
        }
    }
}
