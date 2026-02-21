<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ParkingLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_lot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/lots', [
                'name' => 'Test Parking Lot',
                'address' => '123 Main St',
                'total_slots' => 20,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('parking_lots', ['name' => 'Test Parking Lot']);
    }

    public function test_user_can_list_lots(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        ParkingLot::create([
            'name' => 'Lot 1',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/lots');

        $response->assertStatus(200);
    }

    public function test_user_can_get_lot_details(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $lot = ParkingLot::create([
            'name' => 'Lot 1',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/lots/' . $lot->id);

        $response->assertStatus(200)
            ->assertJson(['name' => 'Lot 1']);
    }

    public function test_admin_can_update_lot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $lot = ParkingLot::create([
            'name' => 'Old Name',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/lots/' . $lot->id, [
                'name' => 'New Name',
                'total_slots' => 15,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('parking_lots', ['name' => 'New Name']);
    }

    public function test_admin_can_delete_lot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $lot = ParkingLot::create([
            'name' => 'To Delete',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/lots/' . $lot->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('parking_lots', ['id' => $lot->id]);
    }
}
