<?php

namespace Tests\Integration;

use App\Models\Booking;
use App\Models\Vehicle;

class ApiContractTest extends IntegrationTestCase
{
    // ── Pagination envelope ──────────────────────────────────────────────

    public function test_bookings_list_returns_pagination_envelope(): void
    {
        $lot = $this->createLotWithSlots(3);
        $slots = $lot->slots;

        foreach ($slots as $i => $slot) {
            Booking::create([
                'user_id' => $this->regularUser->id,
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDays($i + 1)->setHour(9),
                'end_time' => now()->addDays($i + 1)->setHour(17),
                'booking_type' => 'single',
                'status' => 'confirmed',
            ]);
        }

        $response = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/bookings');

        $response->assertStatus(200);

        // Response must have data array at minimum
        $body = $response->json();
        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['data']);
    }

    public function test_bookings_co2_summary_returns_rust_compatible_shape(): void
    {
        $lot = $this->createLotWithSlots(2);
        $slots = $lot->slots;
        Vehicle::create([
            'user_id' => $this->regularUser->id,
            'plate' => 'EV-CO2',
            'vehicle_type' => 'electric',
            'is_default' => true,
        ]);

        Booking::create([
            'user_id' => $this->regularUser->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'vehicle_plate' => 'EV-CO2',
            'start_time' => now()->subDay()->setHour(9)->setMinute(0)->setSecond(0),
            'end_time' => now()->subDay()->setHour(17)->setMinute(0)->setSecond(0),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);
        Booking::create([
            'user_id' => $this->regularUser->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[1]->id,
            'vehicle_plate' => 'EV-CO2',
            'start_time' => now()->subDay()->setHour(9)->setMinute(15)->setSecond(0),
            'end_time' => now()->subDay()->setHour(17)->setMinute(0)->setSecond(0),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $response = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/bookings/co2-summary');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bookings_counted', 2)
            ->assertJsonStructure([
                'data' => [
                    'from',
                    'to',
                    'bookings_counted',
                    'total_km',
                    'emitted_g',
                    'counterfactual_g',
                    'saved_g',
                    'carpool_saved_g',
                    'saved_kg',
                ],
            ]);

        $this->assertIsNumeric($response->json('data.saved_kg'));
        $this->assertGreaterThan(0, $response->json('data.saved_g'));
        $this->assertGreaterThan(0, $response->json('data.carpool_saved_g'));
        $this->assertLessThan($response->json('data.counterfactual_g'), $response->json('data.emitted_g'));
    }

    public function test_bookings_co2_summary_rejects_invalid_query_params(): void
    {
        $response = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/bookings/co2-summary?from=not-a-date&lot_id=not-a-uuid');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_bookings_ical_route_is_not_captured_by_booking_id_route(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        Booking::create([
            'user_id' => $this->regularUser->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->addDay()->setHour(9),
            'end_time' => now()->addDay()->setHour(17),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $response = $this->withHeaders($this->userHeaders())
            ->get('/api/v1/bookings/ical');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/calendar', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
    }

    public function test_admin_users_list_returns_data_array(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['data']);
    }

    public function test_lots_list_returns_data_array(): void
    {
        $this->createLotWithSlots(5);

        $response = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/lots');

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertArrayHasKey('data', $body);
    }

    // ── Error envelope ───────────────────────────────────────────────────

    public function test_validation_error_returns_422_with_errors_object(): void
    {
        $response = $this->withHeaders($this->userHeaders())
            ->postJson('/api/v1/bookings', []);

        $response->assertStatus(422);
        $body = $response->json();

        // The API wraps errors: {success, data, error: {code, message}, meta}
        $this->assertArrayHasKey('success', $body);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('error', $body);
        $this->assertIsArray($body['error']);
        $this->assertArrayHasKey('message', $body['error']);
    }

    public function test_not_found_returns_404_with_message(): void
    {
        $response = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/bookings/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/bookings');

        $response->assertStatus(401);
    }

    public function test_forbidden_returns_403(): void
    {
        $response = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(403);
    }

    // ── Content-Type headers ─────────────────────────────────────────────

    public function test_json_endpoints_return_json_content_type(): void
    {
        $response = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/lots');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_health_endpoint_returns_200_with_json(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    // ── Major endpoint contract shapes ───────────────────────────────────

    public function test_me_endpoint_returns_user_shape(): void
    {
        $response = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/me');

        $response->assertStatus(200);
        $body = $response->json();
        $data = $body['data'] ?? $body;
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('name', $data);
    }

    public function test_lot_show_returns_lot_shape(): void
    {
        $lot = $this->createLotWithSlots(2);

        $response = $this->withHeaders($this->userHeaders())
            ->getJson("/api/v1/lots/{$lot->id}");

        $response->assertStatus(200);
        $body = $response->json();
        $data = $body['data'] ?? $body;
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('total_slots', $data);
    }

    public function test_lot_slots_returns_array_of_slots(): void
    {
        $lot = $this->createLotWithSlots(3);

        $response = $this->withHeaders($this->userHeaders())
            ->getJson("/api/v1/lots/{$lot->id}/slots");

        $response->assertStatus(200);
        $body = $response->json();
        $data = $body['data'] ?? $body;
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('slot_number', $first);
        $this->assertArrayHasKey('status', $first);
    }

    public function test_booking_create_returns_booking_shape(): void
    {
        $lot = $this->createLotWithSlots(1);
        $slot = $lot->slots()->first();

        $response = $this->withHeaders($this->userHeaders())
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDay()->setHour(9)->toISOString(),
                'end_time' => now()->addDay()->setHour(17)->toISOString(),
                'booking_type' => 'single',
            ]);

        $response->assertStatus(201);
        $body = $response->json();
        $data = $body['data'] ?? $body;
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('lot_id', $data);
        $this->assertArrayHasKey('user_id', $data);
    }

    public function test_vehicles_list_returns_data_array(): void
    {
        $response = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/vehicles');

        $response->assertStatus(200);
        $body = $response->json();
        $data = $body['data'] ?? $body;
        $this->assertIsArray($data);
    }

    public function test_occupancy_public_endpoint_returns_lots(): void
    {
        $this->createLotWithSlots(5);

        $response = $this->getJson('/api/v1/public/occupancy');

        $response->assertStatus(200);
        $body = $response->json();
        $data = $body['data'] ?? $body;
        $this->assertIsArray($data);
    }

    public function test_team_endpoint_returns_data_array(): void
    {
        $response = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/team');

        $response->assertStatus(200);
        $body = $response->json();
        $data = $body['data'] ?? $body;
        $this->assertIsArray($data);
    }

    public function test_absences_endpoint_returns_data_array(): void
    {
        $response = $this->withHeaders($this->userHeaders())
            ->getJson('/api/v1/absences');

        $response->assertStatus(200);
        $body = $response->json();
        $data = $body['data'] ?? $body;
        $this->assertIsArray($data);
    }

    public function test_admin_bookings_endpoint_returns_data(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/bookings');

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertArrayHasKey('data', $body);
    }

    public function test_admin_audit_log_returns_data(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/audit-log');

        $response->assertStatus(200);
        $body = $response->json();
        $data = $body['data'] ?? $body;
        $this->assertIsArray($data);
    }
}
