<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendPasswordResetNotificationJob;
use App\Mail\PasswordResetEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendPasswordResetNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_password_reset_email(): void
    {
        Mail::fake();

        $job = new SendPasswordResetNotificationJob(
            'user@example.com',
            'Test User',
            'reset-token-123',
            'https://parkhub.test'
        );

        $job->handle();

        Mail::assertQueued(PasswordResetEmail::class, function ($mail) {
            return $mail->hasTo('user@example.com');
        });
    }

    public function test_email_contains_correct_data(): void
    {
        Mail::fake();

        $job = new SendPasswordResetNotificationJob(
            'admin@test.com',
            'Admin User',
            'token-xyz',
            'https://app.parkhub.test'
        );

        $job->handle();

        Mail::assertQueued(PasswordResetEmail::class, function ($mail) {
            return $mail->recipientName === 'Admin User'
                && $mail->resetToken === 'token-xyz'
                && $mail->appUrl === 'https://app.parkhub.test';
        });
    }
}
