<?php

namespace Tests\Unit\Mail;

use App\Mail\WaitlistSlotAvailableMail;
use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistSlotAvailableMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_envelope_has_correct_subject(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $lot = ParkingLot::create(['name' => 'Main Lot', 'total_slots' => 10]);

        $mail = new WaitlistSlotAvailableMail($user, $lot);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('verfügbar', $envelope->subject);
    }

    public function test_subject_includes_lot_name(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'East Wing', 'total_slots' => 5]);

        $mail = new WaitlistSlotAvailableMail($user, $lot);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('East Wing', $envelope->subject);
    }

    public function test_content_includes_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Anna Schmidt']);
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);

        $mail = new WaitlistSlotAvailableMail($user, $lot);
        $content = $mail->content();

        $this->assertStringContainsString('Anna Schmidt', $content->htmlString);
    }

    public function test_content_includes_lot_name(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Garage A', 'total_slots' => 5]);

        $mail = new WaitlistSlotAvailableMail($user, $lot);
        $content = $mail->content();

        $this->assertStringContainsString('Garage A', $content->htmlString);
    }

    public function test_content_is_valid_html(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);

        $mail = new WaitlistSlotAvailableMail($user, $lot);
        $content = $mail->content();

        $this->assertStringContainsString('<!DOCTYPE html>', $content->htmlString);
    }

    public function test_escapes_lot_name_in_html(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Lot <script>alert(1)</script>', 'total_slots' => 5]);

        $mail = new WaitlistSlotAvailableMail($user, $lot);
        $content = $mail->content();

        $this->assertStringNotContainsString('<script>', $content->htmlString);
    }
}
