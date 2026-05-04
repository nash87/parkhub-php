<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
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
            ])->timeout(15)->connectTimeout(5)
                ->get('https://api.github.com/repos/'.self::GITHUB_REPO.'/releases/latest');

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
        $remote = (string) config('parkhub.updates.remote', 'origin');
        $branch = (string) config('parkhub.updates.branch', 'main');
        $currentVersion = $this->currentVersion();

        try {
            // Pull latest code (Process facade uses proc_open, not shell exec)
            $gitResult = Process::path(base_path())
                ->run(['git', 'pull', $remote, $branch]);

            if (! $gitResult->successful()) {
                return $this->failedUpdateResponse('apply', 'GIT_ERROR', 'git pull failed: '.$this->processError($gitResult), [
                    'from_version' => $currentVersion,
                    'remote' => $remote,
                    'branch' => $branch,
                ]);
            }

            // Install composer dependencies before running the updated code's migrations.
            $composerResult = $this->composerInstall();

            if (! $composerResult->successful()) {
                return $this->failedUpdateResponse('apply', 'COMPOSER_ERROR', 'composer install failed: '.$this->processError($composerResult), [
                    'from_version' => $currentVersion,
                    'remote' => $remote,
                    'branch' => $branch,
                ]);
            }

            // Run migrations
            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                return $this->failedUpdateResponse('apply', 'ARTISAN_ERROR', 'migrate failed', [
                    'from_version' => $currentVersion,
                    'remote' => $remote,
                    'branch' => $branch,
                ]);
            }

            // Clear caches
            foreach (['config:cache', 'route:cache', 'view:clear'] as $command) {
                if (Artisan::call($command) !== 0) {
                    return $this->failedUpdateResponse('apply', 'ARTISAN_ERROR', "{$command} failed", [
                        'from_version' => $currentVersion,
                        'remote' => $remote,
                        'branch' => $branch,
                    ]);
                }
            }

            $newVersion = $this->currentVersion();
            $this->appendHistory('apply', 'success', [
                'from_version' => $currentVersion,
                'version' => $newVersion,
                'remote' => $remote,
                'branch' => $branch,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'update_applied',
                    'version' => $newVersion,
                    'message' => "Updated to v{$newVersion}. Application caches refreshed.",
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->failedUpdateResponse('apply', 'UPDATE_ERROR', $e->getMessage(), [
                'from_version' => $currentVersion,
                'remote' => $remote,
                'branch' => $branch,
            ]);
        }
    }

    /**
     * GET /api/v1/admin/updates/history
     * List previous version updates.
     */
    public function history(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->readHistory()]);
    }

    /**
     * GET /api/v1/admin/updates/releases
     * List all available GitHub releases.
     */
    #[OpenApiResponse(
        status: 200,
        type: 'array{success: bool, data: list<array{version: string, tag: string, name: string, published_at: string, prerelease: bool, url: string, is_current: bool}>}',
    )]
    public function releases(): JsonResponse
    {
        $currentVersion = trim(file_get_contents(base_path('VERSION')));

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'ParkHub-PHP',
                'Accept' => 'application/vnd.github.v3+json',
            ])->timeout(15)->connectTimeout(5)
                ->get('https://api.github.com/repos/'.self::GITHUB_REPO.'/releases?per_page=20');

            if (! $response->successful()) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $releases = [];
            foreach ($response->json() as $release) {
                $tagName = (string) ($release['tag_name'] ?? '');
                $version = ltrim($tagName, 'v');
                $releases[] = [
                    'version' => $version,
                    'tag' => $tagName,
                    'name' => (string) ($release['name'] ?? ''),
                    'published_at' => (string) ($release['published_at'] ?? ''),
                    'prerelease' => (bool) ($release['prerelease'] ?? false),
                    'url' => (string) ($release['html_url'] ?? ''),
                    'is_current' => $version === $currentVersion,
                ];
            }

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
        $currentVersion = $this->currentVersion();

        if (! $version) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MISSING_VERSION', 'message' => 'Version is required'],
            ], 400);
        }

        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_VERSION', 'message' => 'Invalid version format'],
            ], 422);
        }

        try {
            // Checkout the specific tag
            $result = Process::path(base_path())
                ->run(['git', 'checkout', "v{$version}"]);

            if (! $result->successful()) {
                return $this->failedUpdateResponse('rollback', 'GIT_ERROR', 'Rollback failed: '.$this->processError($result), [
                    'from_version' => $currentVersion,
                    'version' => $version,
                ]);
            }

            $composerResult = $this->composerInstall();

            if (! $composerResult->successful()) {
                return $this->failedUpdateResponse('rollback', 'COMPOSER_ERROR', 'composer install failed: '.$this->processError($composerResult), [
                    'from_version' => $currentVersion,
                    'version' => $version,
                ]);
            }

            // Run migrations
            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                return $this->failedUpdateResponse('rollback', 'ARTISAN_ERROR', 'migrate failed', [
                    'from_version' => $currentVersion,
                    'version' => $version,
                ]);
            }

            foreach (['config:cache', 'route:cache', 'view:clear'] as $command) {
                if (Artisan::call($command) !== 0) {
                    return $this->failedUpdateResponse('rollback', 'ARTISAN_ERROR', "{$command} failed", [
                        'from_version' => $currentVersion,
                        'version' => $version,
                    ]);
                }
            }

            $this->appendHistory('rollback', 'success', [
                'from_version' => $currentVersion,
                'version' => (string) $version,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'rolled_back',
                    'version' => $version,
                    'message' => "Rolled back to v{$version}.",
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->failedUpdateResponse('rollback', 'ROLLBACK_ERROR', $e->getMessage(), [
                'from_version' => $currentVersion,
                'version' => (string) $version,
            ]);
        }
    }

    private function currentVersion(): string
    {
        return trim(file_get_contents(base_path('VERSION')));
    }

    private function composerInstall(): ProcessResultContract
    {
        return Process::path(base_path())
            ->run(['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction']);
    }

    private function failedUpdateResponse(string $action, string $code, string $message, array $context): JsonResponse
    {
        $this->appendHistory($action, 'failed', [
            ...$context,
            'error_code' => $code,
            'message' => $message,
        ]);

        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], 500);
    }

    private function processError(ProcessResultContract $result): string
    {
        $error = trim($result->errorOutput());
        if ($error !== '') {
            return $error;
        }

        $output = trim($result->output());
        if ($output !== '') {
            return $output;
        }

        return 'exit code '.(string) $result->exitCode();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readHistory(): array
    {
        $historyFile = $this->historyPath();
        if (! file_exists($historyFile)) {
            return [];
        }

        $history = json_decode((string) file_get_contents($historyFile), true);

        return is_array($history) ? array_values($history) : [];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function appendHistory(string $action, string $status, array $context): void
    {
        $history = $this->readHistory();
        $history[] = [
            'action' => $action,
            'status' => $status,
            'created_at' => now()->toISOString(),
            ...$context,
        ];

        $historyFile = $this->historyPath();
        $directory = dirname($historyFile);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function historyPath(): string
    {
        return storage_path('app/update_history.json');
    }
}
