<?php

namespace Tests\Unit\Mail;

use App\Mail\PasswordResetEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_envelope_has_correct_subject(): void
    {
        $mail = new PasswordResetEmail('Test User', 'token123', 'https://app.test');
        $envelope = $mail->envelope();

        $this->assertStringContainsString('Passwort zurücksetzen', $envelope->subject);
    }

    public function test_content_includes_recipient_name(): void
    {
        $mail = new PasswordResetEmail('Max Mustermann', 'token123', 'https://app.test');
        $content = $mail->content();

        $this->assertStringContainsString('Max Mustermann', $content->htmlString);
    }

    public function test_content_includes_reset_link(): void
    {
        $mail = new PasswordResetEmail('Test User', 'my-reset-token', 'https://app.test');
        $content = $mail->content();

        $this->assertStringContainsString('reset-password', $content->htmlString);
        $this->assertStringContainsString('my-reset-token', $content->htmlString);
    }

    public function test_content_includes_app_url(): void
    {
        $mail = new PasswordResetEmail('Test User', 'token', 'https://parkhub.example.com');
        $content = $mail->content();

        $this->assertStringContainsString('parkhub.example.com', $content->htmlString);
    }

    public function test_escapes_recipient_name_in_html(): void
    {
        $mail = new PasswordResetEmail('User <script>xss</script>', 'token', 'https://app.test');
        $content = $mail->content();

        $this->assertStringNotContainsString('<script>', $content->htmlString);
    }

    public function test_content_is_valid_html(): void
    {
        $mail = new PasswordResetEmail('Test', 'token', 'https://app.test');
        $content = $mail->content();

        $this->assertStringContainsString('<!DOCTYPE html>', $content->htmlString);
    }
}
