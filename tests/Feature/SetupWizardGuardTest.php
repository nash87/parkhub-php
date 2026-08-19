<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Once setup is complete the setup endpoints must stop accepting writes.
 *
 * `/setup`, `/setup/complete` and `/setup/change-password` each carried that
 * guard; `/setup/wizard` did not. Because the whole group is unauthenticated
 * by design (there is no admin yet during first-run) and the module defaults
 * to enabled, the wizard stayed writable for the entire life of a
 * deployment.
 */
class SetupWizardGuardTest extends TestCase
{
    use RefreshDatabase;

    /** Every setup route that mutates state, so a new one cannot be added unguarded. */
    private const MUTATING_SETUP_ROUTES = [
        ['POST', '/api/v1/setup/wizard', ['step' => 1, 'company_name' => 'Attacker GmbH']],
        ['POST', '/api/v1/setup/complete', ['company_name' => 'Attacker GmbH']],
        ['POST', '/api/v1/setup/change-password', ['current_password' => 'x', 'new_password' => 'password123']],
    ];

    public function test_no_setup_route_mutates_state_once_setup_is_completed(): void
    {
        Setting::set('setup_completed', 'true');
        Setting::set('company_name', 'Real Company');

        foreach (self::MUTATING_SETUP_ROUTES as [$method, $uri, $payload]) {
            $response = $this->json($method, $uri, $payload);

            $this->assertContains(
                $response->status(),
                [400, 403, 404, 409],
                "{$method} {$uri} accepted a write after setup was completed (status {$response->status()}).",
            );
        }

        $this->assertSame('Real Company', Setting::get('company_name'));
    }

    /**
     * The most damaging step: it creates active user accounts. Before the
     * guard, an unauthenticated caller could provision accounts on a fully
     * configured instance.
     */
    public function test_wizard_cannot_create_users_once_setup_is_completed(): void
    {
        Setting::set('setup_completed', 'true');
        $before = User::count();

        $response = $this->postJson('/api/v1/setup/wizard', [
            'step' => 3,
            'invite_emails' => ['intruder@example.test'],
        ]);

        $this->assertContains($response->status(), [400, 403, 409]);
        $this->assertSame($before, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.test']);
    }

    /** First-run must keep working: the guard only closes after completion. */
    public function test_wizard_still_works_before_setup_is_completed(): void
    {
        Setting::set('setup_completed', 'false');

        $this->postJson('/api/v1/setup/wizard', [
            'step' => 1,
            'company_name' => 'Legit Company',
        ])->assertOk();

        $this->assertSame('Legit Company', Setting::get('company_name'));
    }

    /** Read-only status stays available so a client can tell setup is done. */
    public function test_wizard_status_remains_readable_after_completion(): void
    {
        Setting::set('setup_completed', 'true');

        $this->getJson('/api/v1/setup/wizard/status')->assertOk();
    }
}
