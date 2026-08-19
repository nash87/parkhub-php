<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What one colleague may learn about another.
 *
 * `booking_visibility` was honoured on exactly one endpoint. Two siblings
 * returning the same population ignored it, and both are reachable by any
 * authenticated user with no admin middleware — so an operator who set
 * `booking_visibility=initials` to satisfy a works-council or GDPR
 * requirement, verified it on the Team page, and shipped, was still
 * disclosing everything through the other two.
 */
class TeamPrivacyDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private function colleague(string $name = 'Dana Meyer'): User
    {
        return User::factory()->create(['role' => 'user', 'name' => $name]);
    }

    private function viewer(string $role = 'user'): array
    {
        $user = User::factory()->create(['role' => $role, 'name' => 'Viewer Person']);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    private function sickToday(User $user, string $note = 'chemo appointment'): Absence
    {
        return Absence::create([
            'user_id' => $user->id,
            'absence_type' => 'sick',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'note' => $note,
        ]);
    }

    // ── /team/today ───────────────────────────────────────────────────────

    public function test_team_today_masks_colleague_names_per_booking_visibility(): void
    {
        Setting::set('booking_visibility', 'initials');
        $dana = $this->colleague();
        $this->sickToday($dana);
        [, $token] = $this->viewer();

        $body = json_encode($this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/team/today')->assertOk()->json());

        $this->assertStringNotContainsString('Dana Meyer', (string) $body);
    }

    /**
     * "Who is in the office today" is scheduling information. *Why* someone
     * is away is health data when the answer is `sick`, and it is not
     * needed to answer the question the page exists to answer.
     */
    public function test_team_today_does_not_disclose_why_a_colleague_is_absent(): void
    {
        $dana = $this->colleague();
        $this->sickToday($dana);
        [, $token] = $this->viewer();

        $body = json_encode($this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/team/today')->assertOk()->json());

        $this->assertStringNotContainsString('sick', (string) $body, 'sick leave was disclosed to a colleague');
    }

    public function test_admins_still_see_the_absence_reason(): void
    {
        $dana = $this->colleague();
        $this->sickToday($dana);
        [, $token] = $this->viewer('admin');

        $body = json_encode($this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/team/today')->assertOk()->json());

        $this->assertStringContainsString('sick', (string) $body, 'an admin lost information they need');
    }

    // ── /absences/team ────────────────────────────────────────────────────

    public function test_team_absences_never_returns_the_private_note(): void
    {
        $dana = $this->colleague();
        $this->sickToday($dana, 'chemo appointment');
        [, $token] = $this->viewer();

        $body = json_encode($this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/absences/team')->assertOk()->json());

        $this->assertStringNotContainsString('chemo', (string) $body, 'a private absence note was disclosed');
    }

    /**
     * `from`/`to` were taken off the query string unvalidated and unbounded,
     * so one request dumped every absence the instance had ever held.
     */
    public function test_team_absences_rejects_an_unbounded_window(): void
    {
        [, $token] = $this->viewer();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/absences/team?from=1900-01-01&to=2999-12-31')
            ->assertStatus(422);
    }

    public function test_team_absences_rejects_a_malformed_date(): void
    {
        [, $token] = $this->viewer();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/absences/team?from=not-a-date&to=also-not')
            ->assertStatus(422);
    }

    public function test_team_absences_still_works_for_a_reasonable_window(): void
    {
        $dana = $this->colleague();
        $this->sickToday($dana);
        [, $token] = $this->viewer();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/absences/team?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString())
            ->assertOk();
    }

    // ── lot layout ────────────────────────────────────────────────────────

    public function test_lot_layout_does_not_disclose_another_users_plate(): void
    {
        $lot = ParkingLot::create(['name' => 'Priv Lot', 'total_slots' => 4, 'available_slots' => 4, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'P1', 'status' => 'available']);
        $dana = $this->colleague();

        Booking::create([
            'user_id' => $dana->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => 'P1',
            'vehicle_plate' => 'B-XX-9999',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
            'status' => Booking::STATUS_CONFIRMED,
            'booking_type' => 'standard',
        ]);

        Setting::set('booking_visibility', 'initials');
        [, $token] = $this->viewer();

        $body = json_encode($this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/lots/{$lot->id}")->assertOk()->json());

        $this->assertStringNotContainsString('B-XX-9999', (string) $body, 'another user\'s licence plate was disclosed');
        $this->assertStringNotContainsString('Dana Meyer', (string) $body);
    }
}
