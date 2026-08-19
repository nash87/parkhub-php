<?php

declare(strict_types=1);

namespace Tests\Feature\Docker;

use Tests\TestCase;

/**
 * `docker-entrypoint.sh` must chown Laravel's writable paths to the user
 * Apache actually runs its workers as. Hardcoding `www-data` produced
 * nash87/parkhub-php#578: on any image where Apache falls back to its
 * built-in default user, every request touching the session, cache or log
 * failed with `permission denied`, and because the entrypoint re-applied
 * the wrong ownership on every start, an operator's manual `chown` looked
 * like it silently reverted.
 *
 * The resolution helper is exercised directly by sourcing the entrypoint
 * in library-only mode, so the behaviour is covered by a suite that
 * actually runs in CI rather than by a shell script nobody invokes.
 */
class EntrypointRuntimeIdentityTest extends TestCase
{
    private string $workdir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workdir = sys_get_temp_dir().'/parkhub-entrypoint-'.bin2hex(random_bytes(6));
        mkdir($this->workdir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workdir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->workdir);
        parent::tearDown();
    }

    /**
     * @param  array{envvars?: string, conf?: string}  $files
     */
    private function resolve(array $files): string
    {
        $envvars = $this->workdir.'/envvars-absent';
        $conf = $this->workdir.'/conf-absent';

        if (isset($files['envvars'])) {
            $envvars = $this->workdir.'/envvars';
            file_put_contents($envvars, $files['envvars']);
        }
        if (isset($files['conf'])) {
            $conf = $this->workdir.'/apache2.conf';
            file_put_contents($conf, $files['conf']);
        }

        $script = base_path('docker-entrypoint.sh');
        $cmd = sprintf(
            'env -u APACHE_RUN_USER -u APACHE_RUN_GROUP PARKHUB_ENTRYPOINT_LIB_ONLY=1 APACHE_ENVVARS_FILE=%s APACHE_CONF_FILE=%s bash -c %s 2>/dev/null',
            escapeshellarg($envvars),
            escapeshellarg($conf),
            escapeshellarg('. '.$script.'; resolve_apache_identity'),
        );

        return trim((string) shell_exec($cmd));
    }

    public function test_prefers_the_identity_declared_in_apache_envvars(): void
    {
        $this->assertSame('www-data:www-data', $this->resolve([
            'envvars' => "export APACHE_RUN_USER=www-data\nexport APACHE_RUN_GROUP=www-data\n",
        ]));
    }

    /**
     * The regression that #578 describes: workers running as `daemon`
     * while the entrypoint chowned to `www-data`.
     */
    public function test_resolves_a_non_www_data_runtime_user(): void
    {
        $this->assertSame('daemon:daemon', $this->resolve([
            'envvars' => "export APACHE_RUN_USER=daemon\nexport APACHE_RUN_GROUP=daemon\n",
        ]));
    }

    public function test_falls_back_to_a_literal_user_directive_in_apache_conf(): void
    {
        $this->assertSame('daemon:daemon', $this->resolve([
            'conf' => "ServerRoot \"/etc/apache2\"\nUser daemon\nGroup daemon\n",
        ]));
    }

    /**
     * An unexpanded `${APACHE_RUN_USER}` placeholder says nothing about the
     * effective user, so it must not be mistaken for one.
     */
    public function test_ignores_unexpanded_placeholders_in_apache_conf(): void
    {
        $this->assertSame('www-data:www-data', $this->resolve([
            'conf' => "User \${APACHE_RUN_USER}\nGroup \${APACHE_RUN_GROUP}\n",
        ]));
    }

    public function test_defaults_to_www_data_when_nothing_declares_an_identity(): void
    {
        $this->assertSame('www-data:www-data', $this->resolve([]));
    }

    public function test_entrypoint_no_longer_hardcodes_a_chown_target(): void
    {
        // Only executable lines carry the invariant. The file deliberately
        // documents the old hardcoded behaviour in comments, and describing
        // a bug must not be able to fail the build.
        $script = collect(preg_split('/\R/', (string) file_get_contents(base_path('docker-entrypoint.sh'))) ?: [])
            ->reject(fn (string $line) => str_starts_with(ltrim($line), '#'))
            ->implode("\n");

        $this->assertStringNotContainsString(
            'chown -R www-data:www-data',
            $script,
            'The entrypoint must chown to the resolved runtime user, not a hardcoded one.',
        );
        $this->assertStringNotContainsString(
            'gosu www-data',
            $script,
            'The scheduler must drop to the resolved runtime user, not a hardcoded one.',
        );
    }
}
