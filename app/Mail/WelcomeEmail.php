<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}

    public function envelope(): Envelope
    {
        $company = Setting::get('company_name', 'ParkHub');
        return new Envelope(
            subject: "[{$company}] Willkommen – Ihr Konto wurde erstellt",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $company = e(Setting::get('company_name', 'ParkHub'));
        $name    = e($this->user->name);
        $email   = e($this->user->email);

        return <<<HTML
<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:32px;">
  <div style="max-width:560px;margin:0 auto;background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.08);">
    <div style="background:#d97706;padding:32px;text-align:center;">
      <h1 style="color:white;margin:0;font-size:24px;font-weight:700;">{$company}</h1>
      <p style="color:rgba(255,255,255,.85);margin:8px 0 0;font-size:14px;">Willkommen bei ParkHub</p>
    </div>
    <div style="padding:32px;">
      <p style="color:#374151;font-size:16px;">Hallo {$name},</p>
      <p style="color:#374151;font-size:14px;line-height:1.6;">
        Ihr Konto wurde erfolgreich erstellt. Sie können sich ab sofort mit Ihrer E-Mail-Adresse
        <strong>{$email}</strong> anmelden und Stellplätze buchen.
      </p>
      <div style="background:#f0fdf4;border-radius:8px;padding:20px;margin:24px 0;border:1px solid #bbf7d0;">
        <p style="color:#166534;font-size:14px;margin:0;font-weight:600;">✓ Konto aktiviert</p>
        <p style="color:#166534;font-size:13px;margin:8px 0 0;">
          Sie haben jetzt Zugriff auf alle Buchungsfunktionen der Plattform.
        </p>
      </div>
      <p style="color:#374151;font-size:14px;line-height:1.6;">
        Bei Fragen wenden Sie sich bitte an den Betreiber dieser ParkHub-Instanz.
      </p>
    </div>
    <div style="background:#f9fafb;padding:16px 32px;text-align:center;border-top:1px solid #e5e7eb;">
      <p style="color:#9ca3af;font-size:12px;margin:0;">{$company} · ParkHub Open Source Parking Platform</p>
    </div>
  </div>
</body></html>
HTML;
    }
}
