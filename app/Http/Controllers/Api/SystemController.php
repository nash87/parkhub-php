<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function version(Request $request)
    {
        return response()->json(self::appRelease());
    }

    /**
     * Read application version from the VERSION file (single source of truth).
     */
    public static function appVersion(): string
    {
        static $version = null;
        if ($version === null) {
            $versionFile = base_path('VERSION');
            $version = file_exists($versionFile)
                ? trim(file_get_contents($versionFile))
                : '0.0.0';
        }

        return $version;
    }

    public static function appBuild(): string
    {
        return (string) config('app.build', 'php-laravel');
    }

    /**
     * Canonical version/build payload for health + version endpoints.
     *
     * @return array{version: string, build: string}
     */
    public static function appRelease(): array
    {
        return [
            'version' => self::appVersion(),
            'build' => self::appBuild(),
        ];
    }

    public function maintenance(Request $request)
    {
        $active = Setting::get('maintenance_mode', 'false') === 'true';

        return response()->json([
            'active' => $active,
            'message' => Setting::get('maintenance_message', ''),
        ]);
    }
}
