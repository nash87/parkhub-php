<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiVersionController extends Controller
{
    /**
     * GET /version — return API version info, supported versions, and deprecation notices.
     */
    public function version(): JsonResponse
    {
        $release = SystemController::appRelease();

        return response()->json([
            'success' => true,
            'data' => [
                'version' => $release['version'],
                'build' => $release['build'],
                'api_prefix' => '/api/v1',
                'status' => 'stable',
                'deprecations' => self::deprecations(),
                'supported_versions' => ['v1'],
            ],
        ]);
    }

    /**
     * GET /changelog — return recent changelog entries.
     */
    public function changelog(): JsonResponse
    {
        $changelogPath = base_path('CHANGELOG.md');
        $content = file_exists($changelogPath)
            ? file_get_contents($changelogPath)
            : '';

        // Parse the first 3 release sections
        $entries = [];
        $sections = preg_split('/^## /m', $content);
        foreach (array_slice($sections, 1, 3) as $section) {
            $lines = explode("\n", trim($section));
            $header = $lines[0] ?? '';
            preg_match('/\[([^\]]+)\]\s*-\s*(.+)/', $header, $matches);
            $entries[] = [
                'version' => $matches[1] ?? 'unknown',
                'date' => $matches[2] ?? 'unknown',
                'body' => trim(implode("\n", array_slice($lines, 1))),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'entries' => $entries,
                'total' => count($entries),
            ],
        ]);
    }

    /**
     * Known deprecation notices for the current API version.
     */
    private static function deprecations(): array
    {
        return [
            [
                'endpoint' => '/api/v1/lots/{id}/slots',
                'method' => 'GET',
                'severity' => 'info',
                'message' => 'Use /api/v1/lots/{id}/display instead for lobby/display data',
                'sunset_date' => '2027-01-01',
                'replacement' => '/api/v1/lots/{id}/display',
            ],
        ];
    }
}
