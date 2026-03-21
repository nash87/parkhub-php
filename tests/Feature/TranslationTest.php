<?php

namespace Tests\Feature;

use App\Models\TranslationOverride;
use App\Models\TranslationProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_overrides_endpoint_is_public(): void
    {
        $response = $this->getJson('/api/v1/translations/overrides');
        $response->assertStatus(200)
            ->assertJsonStructure([]);
    }

    public function test_overrides_returns_all_overrides(): void
    {
        TranslationOverride::create(['language' => 'de', 'key' => 'hello', 'value' => 'Hallo']);
        TranslationOverride::create(['language' => 'fr', 'key' => 'hello', 'value' => 'Bonjour']);

        $response = $this->getJson('/api/v1/translations/overrides');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json());
    }

    public function test_proposals_require_auth(): void
    {
        $this->getJson('/api/v1/translations/proposals')
            ->assertStatus(401);
    }

    public function test_authenticated_user_can_list_proposals(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/translations/proposals');

        $response->assertStatus(200);
    }

    public function test_user_can_create_proposal(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/translations/proposals', [
                'language' => 'de',
                'key' => 'nav.bookings',
                'proposed_value' => 'Buchungen',
                'current_value' => 'Bookings',
                'context' => 'Navigation menu item',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('translation_proposals', [
            'language' => 'de',
            'key' => 'nav.bookings',
            'proposed_value' => 'Buchungen',
            'proposed_by' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_proposal_requires_language_and_key(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/translations/proposals', [
                'proposed_value' => 'Some Value',
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_vote_on_proposal(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $proposal = TranslationProposal::create([
            'language' => 'de',
            'key' => 'test.key',
            'current_value' => 'Test',
            'proposed_value' => 'Prüfung',
            'proposed_by' => $user->id,
            'status' => 'pending',
            'votes_for' => 0,
            'votes_against' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/translations/proposals/{$proposal->id}/vote", [
                'vote' => 'up',
            ]);

        $response->assertStatus(200);
    }

    public function test_admin_can_review_proposal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $proposal = TranslationProposal::create([
            'language' => 'de',
            'key' => 'test.key',
            'current_value' => 'Test',
            'proposed_value' => 'Prüfung',
            'proposed_by' => $user->id,
            'status' => 'pending',
            'votes_for' => 0,
            'votes_against' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/translations/proposals/{$proposal->id}/review", [
                'status' => 'approved',
                'comment' => 'Looks good!',
            ]);

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_review_proposal(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $proposer = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $proposal = TranslationProposal::create([
            'language' => 'de',
            'key' => 'test.key2',
            'current_value' => 'Test',
            'proposed_value' => 'Prüfung',
            'proposed_by' => $proposer->id,
            'status' => 'pending',
            'votes_for' => 0,
            'votes_against' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/translations/proposals/{$proposal->id}/review", [
                'status' => 'approved',
            ]);

        $response->assertStatus(403);
    }

    public function test_proposals_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        TranslationProposal::create([
            'language' => 'de', 'key' => 'k1', 'current_value' => 'A', 'proposed_value' => 'B',
            'proposed_by' => $user->id, 'status' => 'pending', 'votes_for' => 0, 'votes_against' => 0,
        ]);
        TranslationProposal::create([
            'language' => 'de', 'key' => 'k2', 'current_value' => 'C', 'proposed_value' => 'D',
            'proposed_by' => $user->id, 'status' => 'approved', 'votes_for' => 3, 'votes_against' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/translations/proposals?status=pending');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data);
    }
}
