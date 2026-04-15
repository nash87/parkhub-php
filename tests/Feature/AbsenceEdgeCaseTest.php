<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_absence_invalid_type_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/absences', [
                'absence_type' => 'invalid_type',
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addDays(2)->format('Y-m-d'),
            ])
            ->assertStatus(422);
    }

    public function test_absence_missing_dates_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/absences', [
                'absence_type' => 'vacation',
            ])
            ->assertStatus(422);
    }

    public function test_absence_update(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $absence = Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'vacation',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/absences/'.$absence->id, [
                'absence_type' => 'sick',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('absences', ['id' => $absence->id, 'absence_type' => 'sick']);
    }

    public function test_absence_delete(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $absence = Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'training',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/absences/'.$absence->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('absences', ['id' => $absence->id]);
    }

    public function test_cannot_delete_other_users_absence(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token2 = $user2->createToken('test')->plainTextToken;

        $absence = Absence::create([
            'user_id' => $user1->id,
            'absence_type' => 'vacation',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token2)
            ->deleteJson('/api/v1/absences/'.$absence->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('absences', ['id' => $absence->id]);
    }

    public function test_cannot_update_other_users_absence(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token2 = $user2->createToken('test')->plainTextToken;

        $absence = Absence::create([
            'user_id' => $user1->id,
            'absence_type' => 'vacation',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token2)
            ->putJson('/api/v1/absences/'.$absence->id, ['absence_type' => 'sick'])
            ->assertStatus(403);
    }

    public function test_team_absences(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'homeoffice',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/absences/team');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json());
    }

    public function test_absence_pattern_save_and_retrieve(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // The shared TS client expects AbsencePattern[] — an array of
        // {absence_type, weekdays} objects. Post the canonical Rust shape
        // and expect the same thing back on the list endpoint.
        $weekdays = [0, 2, 4]; // Mon, Wed, Fri

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/absences/pattern', [
                'absence_type' => 'homeoffice',
                'weekdays' => $weekdays,
            ])
            ->assertStatus(200)
            ->assertJson([
                'data' => ['absence_type' => 'homeoffice', 'weekdays' => $weekdays],
            ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/absences/pattern');

        $response->assertStatus(200);
        $this->assertEquals(
            [['absence_type' => 'homeoffice', 'weekdays' => $weekdays]],
            $response->json('data'),
        );
    }

    public function test_absence_type_via_type_field(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Send 'type' instead of 'absence_type' (Rust API parity)
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/absences', [
                'type' => 'homeoffice',
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addDays(2)->format('Y-m-d'),
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('absences', [
            'user_id' => $user->id,
            'absence_type' => 'homeoffice',
        ]);
    }

    public function test_absence_list_ordered_by_date(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'vacation',
            'start_date' => now()->addDays(10)->format('Y-m-d'),
            'end_date' => now()->addDays(12)->format('Y-m-d'),
        ]);
        Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'sick',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/absences');

        $response->assertStatus(200);
        $data = $response->json('data');
        // Most recent start_date first (desc order)
        $this->assertGreaterThanOrEqual(
            $data[1]['start_date'] ?? '',
            $data[0]['start_date']
        );
    }

    public function test_absences_require_auth(): void
    {
        $this->getJson('/api/v1/absences')->assertStatus(401);
        $this->postJson('/api/v1/absences', [])->assertStatus(401);
    }
}
