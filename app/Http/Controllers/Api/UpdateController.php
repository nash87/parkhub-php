<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Self-update system: check GitHub Releases for newer versions,
 * download and apply updates from the admin UI.
 */
class UpdateController extends Controller
{
    private const GITHUB_REPO = 'nash87/parkhub-php';

    /**
     * GET /api/v1/admin/updates/check
     * Check GitHub for a newer version.
     */
    public function check(): JsonResponse
    {
        $currentVersion = trim(file_get_contents(base_path('VERSION')));

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'ParkHub-PHP',
                'Accept' => 'application/vnd.github.v3+json',
            ])->get('https://api.github.com/repos/'.self::GITHUB_REPO.'/releases/latest');

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'UPSTREAM_ERROR', 'message' => 'GitHub API error'],
                ], 502);
            }

            $release = $response->json();
            $latestVersion = ltrim($release['tag_name'] ?? 'v0.0.0', 'v');
            $available = version_compare($latestVersion, $currentVersion, '>');

            return response()->json([
                'success' => true,
                'data' => [
                    'available' => $available,
                    'current_version' => $currentVersion,
                    'latest_version' => $latestVersion,
                    'release_url' => $release['html_url'] ?? '',
                    'release_notes' => $release['body'] ?? '',
                    'published_at' => $release['published_at'] ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NETWORK_ERROR', 'message' => 'Failed to reach GitHub'],
            ], 502);
        }
    }

    /**
     * POST /api/v1/admin/updates/apply
     * Apply update via git pull + artisan migrate.
     * Uses Laravel Process facade (no shell injection risk).
     */
    public function apply(Request $request): JsonResponse
    {
        try {
            // Pull latest code (Process facade uses proc_open, not shell exec)
            $gitResult = Process::path(base_path())
                ->run(['git', 'pull', 'origin', 'main']);

            if (! $gitResult->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'GIT_ERROR', 'message' => 'git pull failed: '.$gitResult->errorOutput()],
                ], 500);
            }

            // Run migrations
            Artisan::call('migrate', ['--force' => true]);

            // Clear caches
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:clear');

            // Install composer dependencies
            $composerResult = Process::path(base_path())
                ->run(['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction']);

            $newVersion = trim(file_get_contents(base_path('VERSION')));

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'update_applied',
                    'version' => $newVersion,
                    'message' => "Updated to v{$newVersion}. Application caches refreshed.",
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UPDATE_ERROR', 'message' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/v1/admin/updates/history
     * List previous version updates.
     */
    public function history(): JsonResponse
    {
        $historyFile = storage_path('app/update_history.json');
        $history = file_exists($historyFile)
            ? json_decode(file_get_contents($historyFile), true) ?? []
            : [];

        return response()->json(['success' => true, 'data' => $history]);
    }

    /**
     * GET /api/v1/admin/updates/releases
     * List all available GitHub releases.
     */
    public function releases(): JsonResponse
    {
        $currentVersion = trim(file_get_contents(base_path('VERSION')));

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'ParkHub-PHP',
                'Accept' => 'application/vnd.github.v3+json',
            ])->get('https://api.github.com/repos/'.self::GITHUB_REPO.'/releases?per_page=20');

            if (! $response->successful()) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $releases = collect($response->json())->map(fn ($r) => [
                'version' => ltrim($r['tag_name'] ?? '', 'v'),
                'tag' => $r['tag_name'] ?? '',
                'name' => $r['name'] ?? '',
                'published_at' => $r['published_at'] ?? '',
                'prerelease' => $r['prerelease'] ?? false,
                'url' => $r['html_url'] ?? '',
                'is_current' => ltrim($r['tag_name'] ?? '', 'v') === $currentVersion,
            ])->toArray();

            return response()->json(['success' => true, 'data' => $releases]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    /**
     * POST /api/v1/admin/updates/rollback
     * Revert to a previous version via git checkout.
     */
    public function rollback(Request $request): JsonResponse
    {
        $version = $request->input('version');
        if (! $version) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MISSING_VERSION', 'message' => 'Version is required'],
            ], 400);
        }

        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return response()->json(['error' => ['message' => 'Invalid version format']], 422);
        }

        try {
            // Checkout the specific tag
            $result = Process::path(base_path())
                ->run(['git', 'checkout', "v{$version}"]);

            if (! $result->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'GIT_ERROR', 'message' => 'Rollback failed: '.$result->errorOutput()],
                ], 500);
            }

            // Run migrations
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('config:cache');
            Artisan::call('route:cache');

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'rolled_back',
                    'version' => $version,
                    'message' => "Rolled back to v{$version}.",
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ROLLBACK_ERROR', 'message' => $e->getMessage()],
            ], 500);
        }
    }
}
