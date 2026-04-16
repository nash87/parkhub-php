<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StripeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['modules.stripe' => true]);
    }

    public function test_create_checkout_returns_session_in_stub_mode(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/payments/create-checkout', [
                'credits' => 10,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'checkout_url', 'amount', 'credits', 'currency'],
            ])
            ->assertJsonPath('data.credits', 10);

        // Should create a stripe_payments record
        $this->assertDatabaseHas('stripe_payments', [
            'user_id' => $user->id,
            'credits' => 10,
            'status' => 'pending',
        ]);
    }

    public function test_create_checkout_requires_auth(): void
    {
        $this->postJson('/api/v1/payments/create-checkout', ['credits' => 5])
            ->assertStatus(401);
    }

    public function test_create_checkout_validates_credits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/payments/create-checkout', ['credits' => 0])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson('/api/v1/payments/create-checkout', [])
            ->assertStatus(422);
    }

    public function test_payment_history_returns_user_payments(): void
    {
        $user = User::factory()->create();

        DB::table('stripe_payments')->insert([
            'id' => 'cs_test_123',
            'user_id' => $user->id,
            'amount' => 1000,
            'credits' => 10,
            'currency' => 'eur',
            'status' => 'completed',
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/payments/history');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'cs_test_123')
            ->assertJsonPath('data.0.credits', 10);
    }

    public function test_payment_history_scoped_to_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        DB::table('stripe_payments')->insert([
            'id' => 'cs_user1',
            'user_id' => $user1->id,
            'amount' => 500,
            'credits' => 5,
            'currency' => 'eur',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stripe_payments')->insert([
            'id' => 'cs_user2',
            'user_id' => $user2->id,
            'amount' => 1000,
            'credits' => 10,
            'currency' => 'eur',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user1)
            ->getJson('/api/v1/payments/history');

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'cs_user1');
    }

    public function test_config_status_reports_not_configured(): void
    {
        config(['services.stripe.secret' => null, 'services.stripe.key' => null]);

        $response = $this->getJson('/api/v1/payments/config/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.publishable_key', null);
    }

    public function test_config_status_reports_configured(): void
    {
        config(['services.stripe.secret' => 'sk_test_xxx', 'services.stripe.key' => 'pk_test_xxx']);

        $response = $this->getJson('/api/v1/payments/config/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.publishable_key', 'pk_test_xxx');
    }

    public function test_webhook_accepts_valid_payload(): void
    {
        config(['services.stripe.webhook_secret' => null]); // No verification

        $response = $this->postJson('/api/v1/payments/webhook', [
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_123']],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.received', true);
    }

    public function test_webhook_rejects_bad_signature(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

        $response = $this->withHeaders([
            'Stripe-Signature' => 'invalid',
        ])->postJson('/api/v1/payments/webhook', [
            'type' => 'test',
        ]);

        $response->assertStatus(403);
    }

    public function test_webhook_accepts_valid_hmac_signature(): void
    {
        $secret = 'whsec_test_correct';
        config(['services.stripe.webhook_secret' => $secret]);

        $payload = json_encode(['type' => 'test.event', 'id' => 'evt_test_1']);
        $timestamp = time();
        $sig = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        $response = $this->call(
            'POST',
            '/api/v1/payments/webhook',
            [],
            [],
            [],
            [
                'HTTP_Stripe-Signature' => "t={$timestamp},v1={$sig}",
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        );

        // Anything other than 403 proves the signature passed; unknown event
        // types get a benign 200 from the handler's default branch.
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_webhook_rejects_valid_hmac_on_tampered_body(): void
    {
        // MITM scenario: attacker sees a genuine webhook in transit, computes
        // a valid HMAC for the original body, then rewrites the body to mint
        // free credits for themselves. Signature must not validate.
        $secret = 'whsec_test_correct';
        config(['services.stripe.webhook_secret' => $secret]);

        $originalBody = json_encode(['amount' => 100]);
        $tamperedBody = json_encode(['amount' => 1000000]);
        $timestamp = time();
        $sigForOriginal = hash_hmac('sha256', "{$timestamp}.{$originalBody}", $secret);

        $response = $this->call(
            'POST',
            '/api/v1/payments/webhook',
            [],
            [],
            [],
            [
                'HTTP_Stripe-Signature' => "t={$timestamp},v1={$sigForOriginal}",
                'CONTENT_TYPE' => 'application/json',
            ],
            $tamperedBody,
        );

        $response->assertStatus(403);
    }

    public function test_webhook_rejects_replay_with_stale_timestamp(): void
    {
        // Replay scenario: attacker captured a valid webhook 10 minutes ago
        // and replays it now to double-credit the user. Timestamp delta
        // beyond the 5-minute tolerance must reject, even with a valid HMAC.
        $secret = 'whsec_test_correct';
        config(['services.stripe.webhook_secret' => $secret]);

        $payload = json_encode(['type' => 'test.event']);
        $staleTimestamp = time() - 600; // 10 minutes old
        $sig = hash_hmac('sha256', "{$staleTimestamp}.{$payload}", $secret);

        $response = $this->call(
            'POST',
            '/api/v1/payments/webhook',
            [],
            [],
            [],
            [
                'HTTP_Stripe-Signature' => "t={$staleTimestamp},v1={$sig}",
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        );

        $response->assertStatus(403);
    }

    public function test_stripe_disabled_when_module_off(): void
    {
        config(['modules.stripe' => false]);

        $this->getJson('/api/v1/payments/config/status')
            ->assertStatus(404);
    }

    public function test_webhook_grants_credits_on_completed_session(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $user = User::factory()->create(['credits_balance' => 5]);

        DB::table('stripe_payments')->insert([
            'id' => 'cs_grant_test',
            'user_id' => $user->id,
            'amount' => 2000,
            'credits' => 20,
            'currency' => 'eur',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/payments/webhook', [
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_grant_test']],
        ])->assertStatus(200);

        // Credits should be incremented
        $user->refresh();
        $this->assertEquals(25, $user->credits_balance);

        // Payment status should be completed
        $payment = DB::table('stripe_payments')->where('id', 'cs_grant_test')->first();
        $this->assertEquals('completed', $payment->status);
    }
}
