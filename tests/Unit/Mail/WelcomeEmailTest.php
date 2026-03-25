<?php

namespace Tests\Unit\Mail;

use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_envelope_has_correct_subject(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $mail = new WelcomeEmail($user);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('Willkommen', $envelope->subject);
    }

    public function test_envelope_includes_company_name(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $mail = new WelcomeEmail($user);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('ParkHub', $envelope->subject);
    }

    public function test_content_includes_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Max Mustermann']);
        $mail = new WelcomeEmail($user);
        $content = $mail->content();

        $html = $content->htmlString;
        $this->assertStringContainsString('Max Mustermann', $html);
    }

    public function test_content_includes_user_email(): void
    {
        $user = User::factory()->create(['email' => 'max@test.com']);
        $mail = new WelcomeEmail($user);
        $content = $mail->content();

        $html = $content->htmlString;
        $this->assertStringContainsString('max@test.com', $html);
    }

    public function test_content_is_html(): void
    {
        $user = User::factory()->create();
        $mail = new WelcomeEmail($user);
        $content = $mail->content();

        $this->assertStringContainsString('<!DOCTYPE html>', $content->htmlString);
    }

    public function test_escapes_user_name_in_html(): void
    {
        $user = User::factory()->create(['name' => 'User <script>alert(1)</script>']);
        $mail = new WelcomeEmail($user);
        $content = $mail->content();

        $this->assertStringNotContainsString('<script>', $content->htmlString);
    }
}
