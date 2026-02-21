<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Absence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_absence(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/absences', [
                'type' => 'vacation',
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addDays(5)->format('Y-m-d'),
                'notes' => 'Summer vacation',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('absences', ['user_id' => $user->id, 'type' => 'vacation']);
    }

    public function test_all_absence_types_work(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $types = ['homeoffice', 'vacation', 'sick', 'training', 'other'];

        foreach ($types as $type) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/absences', [
                    'type' => $type,
                    'start_date' => now()->addDay()->format('Y-m-d'),
                    'end_date' => now()->addDays(2)->format('Y-m-d'),
                ]);

            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('absences', 5);
    }
}
