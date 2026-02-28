<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $resetToken,
        public readonly string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        $company = Setting::get('company_name', 'ParkHub');
        return new Envelope(
            subject: "[{$company}] Passwort zurücksetzen",
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
        $company   = e(Setting::get('company_name', 'ParkHub'));
        $name      = e($this->recipientName);
        $resetUrl  = e(rtrim($this->appUrl, '/') . '/reset-password?token=' . urlencode($this->resetToken));
        $expiresIn = '60 Minuten';

        return <<<HTML
<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:32px;">
  <div style="max-width:560px;margin:0 auto;background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.08);">
    <div style="background:#d97706;padding:32px;text-align:center;">
      <h1 style="color:white;margin:0;font-size:24px;font-weight:700;">{$company}</h1>
      <p style="color:rgba(255,255,255,.85);margin:8px 0 0;font-size:14px;">Passwort zurücksetzen</p>
    </div>
    <div style="padding:32px;">
      <p style="color:#374151;font-size:16px;">Hallo {$name},</p>
      <p style="color:#374151;font-size:14px;line-height:1.6;">
        Wir haben eine Anfrage erhalten, das Passwort für Ihr Konto zurückzusetzen.
        Klicken Sie auf den folgenden Button, um ein neues Passwort festzulegen.
      </p>
      <div style="text-align:center;margin:32px 0;">
        <a href="{$resetUrl}"
           style="display:inline-block;background:#d97706;color:white;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;">
          Passwort zurücksetzen
        </a>
      </div>
      <div style="background:#fffbeb;border-radius:8px;padding:16px;border:1px solid #fde68a;margin-bottom:24px;">
        <p style="color:#92400e;font-size:13px;margin:0;">
          <strong>Hinweis:</strong> Dieser Link ist nur {$expiresIn} gültig.
          Wenn Sie keine Passwort-Zurücksetzung angefordert haben, können Sie diese E-Mail ignorieren.
        </p>
      </div>
      <p style="color:#9ca3af;font-size:12px;word-break:break-all;">
        Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:<br>
        <a href="{$resetUrl}" style="color:#d97706;">{$resetUrl}</a>
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
