<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ModuleRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function live()
    {
        return response()->json([
            'status' => 'ok',
            'uptime' => $this->getUptime(),
        ]);
    }

    public function ready()
    {
        $dbStatus = 'ok';
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $dbStatus = 'error';
        }

        $cacheStatus = 'ok';
        try {
            Cache::store()->put('health_check', true, 5);
            if (! Cache::store()->get('health_check')) {
                $cacheStatus = 'error';
            }
            Cache::store()->forget('health_check');
        } catch (\Exception $e) {
            $cacheStatus = 'error';
        }

        $release = SystemController::appRelease();

        $allOk = $dbStatus === 'ok' && $cacheStatus === 'ok';
        $status = $allOk ? 200 : 503;

        return response()->json([
            'status' => $allOk ? 'ok' : 'degraded',
            'database' => $dbStatus,
            'cache' => $cacheStatus,
            'version' => $release['version'],
            'build' => $release['build'],
        ], $status);
    }

    public function info()
    {
        $release = SystemController::appRelease();

        return response()->json([
            'version' => $release['version'],
            'build' => $release['build'],
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'modules' => ModuleRegistry::enabledMap(),
            'uptime' => $this->getUptime(),
        ]);
    }

    private function getUptime(): string
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $uptime = microtime(true) - $startTime;

        if ($uptime < 60) {
            return round($uptime, 2).'s';
        }
        if ($uptime < 3600) {
            return round($uptime / 60, 1).'m';
        }

        return round($uptime / 3600, 1).'h';
    }
}
