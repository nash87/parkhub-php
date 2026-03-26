<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphQLControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(?User $user = null): array
    {
        $user ??= User::factory()->create(['role' => 'user']);

        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function seedLot(): ParkingLot
    {
        return ParkingLot::create([
            'name' => 'GraphQL Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
    }

    public function test_graphql_playground_returns_html(): void
    {
        $response = $this->get('/api/v1/graphql/playground');

        $response->assertStatus(200);
        $this->assertStringContainsString('GraphiQL', $response->getContent());
        $this->assertStringContainsString('graphiql', $response->getContent());
    }

    public function test_graphql_me_query(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', [
                'query' => '{ me { id name email role } }',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['me' => ['id', 'name', 'email', 'role']]]);
    }

    public function test_graphql_lots_query(): void
    {
        $this->seedLot();

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', [
                'query' => '{ lots { id name status } }',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['lots']]);

        $lots = $response->json('data.lots');
        $this->assertNotEmpty($lots);
        $this->assertEquals('GraphQL Test Lot', $lots[0]['name']);
    }

    public function test_graphql_lot_by_id_query(): void
    {
        $lot = $this->seedLot();

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', [
                'query' => "{ lot(id: {$lot->id}) { id name } }",
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.lot.name', 'GraphQL Test Lot');
    }

    public function test_graphql_empty_query_returns_400(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', ['query' => '']);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_graphql_create_booking_mutation(): void
    {
        $lot = $this->seedLot();
        ParkingSlot::factory()->create(['lot_id' => $lot->id, 'status' => 'available']);

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', [
                'query' => 'mutation { createBooking(lot_id: $lot_id) { id status } }',
                'variables' => [
                    'lot_id' => $lot->id,
                    'start_time' => now()->addHour()->toISOString(),
                    'end_time' => now()->addHours(3)->toISOString(),
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['createBooking' => ['id', 'status']]]);

        $this->assertEquals('confirmed', $response->json('data.createBooking.status'));
    }

    public function test_graphql_create_booking_requires_start_time(): void
    {
        $lot = $this->seedLot();

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', [
                'query' => 'mutation { createBooking(lot_id: $lot_id) { id status } }',
                'variables' => ['lot_id' => $lot->id],
            ]);

        // StoreBookingRequest requires start_time — mutation must propagate the 422
        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors']);
    }

    public function test_graphql_create_booking_rejects_past_start_time(): void
    {
        $lot = $this->seedLot();
        ParkingSlot::factory()->create(['lot_id' => $lot->id, 'status' => 'available']);

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', [
                'query' => 'mutation { createBooking(lot_id: $lot_id) { id } }',
                'variables' => [
                    'lot_id' => $lot->id,
                    'start_time' => now()->subHour()->toISOString(),
                    'end_time' => now()->addHour()->toISOString(),
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_graphql_create_booking_enforces_max_active_bookings(): void
    {
        config(['parkhub.max_active_bookings' => 1]);

        $user = User::factory()->create(['role' => 'user']);
        $lot = $this->seedLot();
        ParkingSlot::factory()->create(['lot_id' => $lot->id, 'status' => 'available']);

        // Seed one existing confirmed booking so the user is already at the limit
        Booking::factory()->create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'status' => Booking::STATUS_CONFIRMED,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/graphql', [
                'query' => 'mutation { createBooking(lot_id: $lot_id) { id } }',
                'variables' => [
                    'lot_id' => $lot->id,
                    'start_time' => now()->addDays(2)->toISOString(),
                    'end_time' => now()->addDays(2)->addHours(2)->toISOString(),
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_graphql_create_booking_is_always_for_authenticated_user(): void
    {
        $authenticatedUser = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);

        $lot = $this->seedLot();
        ParkingSlot::factory()->create(['lot_id' => $lot->id, 'status' => 'available']);

        $response = $this->withHeaders($this->authHeader($authenticatedUser))
            ->postJson('/api/v1/graphql', [
                'query' => 'mutation { createBooking(lot_id: $lot_id) { id } }',
                'variables' => [
                    'lot_id' => $lot->id,
                    'start_time' => now()->addHour()->toISOString(),
                    'end_time' => now()->addHours(3)->toISOString(),
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Booking must belong to the authenticated user, not any other user
        $bookingId = $response->json('data.createBooking.id');
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'user_id' => $authenticatedUser->id,
        ]);
        $this->assertDatabaseMissing('bookings', [
            'id' => $bookingId,
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_graphql_create_booking_unauthenticated_returns_401(): void
    {
        $lot = $this->seedLot();

        $response = $this->postJson('/api/v1/graphql', [
            'query' => 'mutation { createBooking(lot_id: $lot_id) { id } }',
            'variables' => [
                'lot_id' => $lot->id,
                'start_time' => now()->addHour()->toISOString(),
                'end_time' => now()->addHours(3)->toISOString(),
            ],
        ]);

        // Unauthenticated → Sanctum returns 401 before we even reach the mutation handler
        $response->assertStatus(401);
    }

    public function test_graphql_my_vehicles_query(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/v1/graphql', [
                'query' => '{ myVehicles { id license_plate } }',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['myVehicles']]);
    }
}
