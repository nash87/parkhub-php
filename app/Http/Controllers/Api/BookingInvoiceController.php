<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Generates a printer-friendly HTML invoice for a completed booking.
 * Users can open the URL in a browser and use "Print → Save as PDF".
 * No external PDF library needed — pure HTML with @media print CSS.
 */
class BookingInvoiceController extends Controller
{
    public function show(Request $request, string $id)
    {
        $user    = $request->user();
        $booking = Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $company     = Setting::get('company_name', 'ParkHub');
        $vatId       = Setting::get('impressum_vat_id', '');
        $street      = Setting::get('impressum_street', '');
        $zipCity     = Setting::get('impressum_zip_city', '');
        $email       = Setting::get('impressum_email', '');

        // Calculate duration
        $start = $booking->start_time ? strtotime($booking->start_time) : 0;
        $end   = $booking->end_time   ? strtotime($booking->end_time)   : $start + 3600;
        $hours = max(1, round(($end - $start) / 3600, 2));

        // Format dates
        $startFmt = $start ? date('d.m.Y H:i', $start) : '—';
        $endFmt   = $end   ? date('d.m.Y H:i', $end)   : '—';
        $dateNow  = date('d.m.Y');

        // Invoice number: INV-YYYYMM-{short_id}
        $shortId = strtoupper(substr(str_replace('-', '', $booking->id), 0, 8));
        $invoiceNo = 'INV-' . date('Ym') . '-' . $shortId;

        $html = $this->renderHtml(compact(
            'company', 'vatId', 'street', 'zipCity', 'email',
            'booking', 'user', 'hours', 'startFmt', 'endFmt',
            'dateNow', 'invoiceNo'
        ));

        return response($html, 200, [
            'Content-Type'        => 'text/html; charset=utf-8',
            'Content-Disposition' => 'inline; filename="rechnung-' . $shortId . '.html"',
            'X-Frame-Options'     => 'SAMEORIGIN',
        ]);
    }

    private function renderHtml(array $d): string
    {
        $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $vatRow = $d['vatId']
            ? '<tr><td>Umsatzsteuer-ID</td><td>' . $e($d['vatId']) . '</td></tr>'
            : '';

        $address = array_filter([$d['street'], $d['zipCity']]);
        $addressHtml = implode('<br>', array_map($e, $address));

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rechnung {$e($d['invoiceNo'])}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 14px; color: #1a1a1a; background: #f5f5f5; }
  .page { max-width: 800px; margin: 32px auto; background: white; padding: 48px; box-shadow: 0 2px 16px rgba(0,0,0,.08); }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; padding-bottom: 24px; border-bottom: 2px solid #e5e7eb; }
  .logo { font-size: 24px; font-weight: 700; color: #d97706; }
  .logo span { color: #374151; }
  .meta { text-align: right; color: #6b7280; font-size: 13px; line-height: 1.6; }
  .meta strong { color: #1a1a1a; font-size: 16px; }
  .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 32px; }
  .party h4 { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #9ca3af; margin-bottom: 8px; }
  .party p { line-height: 1.6; color: #374151; }
  .party strong { color: #1a1a1a; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  th { text-align: left; padding: 12px 16px; background: #f9fafb; font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
  td { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
  tr:last-child td { border-bottom: none; }
  .totals { margin-top: 16px; margin-left: auto; width: 300px; }
  .totals table { font-size: 14px; }
  .totals td { padding: 8px 16px; }
  .totals .total td { font-weight: 700; font-size: 16px; border-top: 2px solid #e5e7eb; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
  .badge-confirmed { background: #d1fae5; color: #065f46; }
  .badge-cancelled { background: #fee2e2; color: #991b1b; }
  .badge-default { background: #f3f4f6; color: #374151; }
  .footer { margin-top: 48px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; text-align: center; line-height: 1.8; }
  .notice { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #92400e; margin-top: 24px; }
  @media print {
    body { background: white; }
    .page { box-shadow: none; margin: 0; padding: 24px; }
    .print-btn { display: none; }
  }
</style>
</head>
<body>
<div class="page">

  <div style="text-align:right; margin-bottom: 12px;" class="print-btn">
    <button onclick="window.print()" style="background:#d97706; color:white; border:none; padding:8px 20px; border-radius:6px; cursor:pointer; font-size:13px;">🖨 Als PDF speichern</button>
  </div>

  <div class="header">
    <div>
      <div class="logo">Park<span>Hub</span></div>
      <div style="margin-top:6px; color:#6b7280; font-size:13px; line-height:1.6;">
        {$addressHtml}
        {$e($d['email'])}
      </div>
    </div>
    <div class="meta">
      <strong>Rechnung</strong><br>
      Nr.: {$e($d['invoiceNo'])}<br>
      Datum: {$e($d['dateNow'])}<br>
      <span style="margin-top:4px; display:inline-block; background:#d97706; color:white; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600;">BEZAHLT</span>
    </div>
  </div>

  <div class="parties">
    <div class="party">
      <h4>Rechnungssteller</h4>
      <p><strong>{$e($d['company'])}</strong><br>{$addressHtml}<br>{$e($d['email'])}</p>
    </div>
    <div class="party">
      <h4>Rechnungsempfänger</h4>
      <p>
        <strong>{$e($d['user']->name)}</strong><br>
        {$e($d['user']->email)}<br>
        {$e($d['user']->username)}
      </p>
    </div>
  </div>

  <h3 style="margin-bottom:16px; font-size:16px; color:#374151;">Leistungsübersicht</h3>
  <table>
    <thead>
      <tr>
        <th>Beschreibung</th>
        <th>Zeitraum</th>
        <th>Dauer</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <strong>Parkplatz-Buchung</strong><br>
          <span style="color:#6b7280; font-size:13px;">
            {$e($d['booking']->lot_name ?? '—')} · Stellplatz {$e($d['booking']->slot_number ?? '—')}
          </span><br>
          <span style="color:#9ca3af; font-size:12px; font-family: monospace;">#{$e($d['booking']->id)}</span>
        </td>
        <td style="white-space:nowrap;">
          {$e($d['startFmt'])}<br>
          <span style="color:#6b7280;">bis {$e($d['endFmt'])}</span>
        </td>
        <td style="white-space:nowrap;">{$e(number_format($d['hours'], 1))} Std.</td>
        <td>
          <span class="badge badge-confirmed">{$e(ucfirst($d['booking']->status ?? 'confirmed'))}</span>
        </td>
      </tr>
    </tbody>
  </table>

  {$vatRow}

  <div class="notice">
    <strong>Hinweis:</strong> Diese Buchungsbestätigung dient als Beleg. Gemäß § 14 UStG wird keine gesonderte Steuer ausgewiesen (Kleinunternehmerregelung oder Betreiber-konfiguriert). Bitte wenden Sie sich bei Fragen an {$e($d['email'])}.
  </div>

  <div class="footer">
    <strong>{$e($d['company'])}</strong> · ParkHub Open Source Parking Platform<br>
    Erstellt am {$e($d['dateNow'])} · Buchungs-ID: {$e($d['booking']->id)}<br>
    <a href="https://github.com/nash87/parkhub-php" style="color:#9ca3af;">github.com/nash87/parkhub-php</a>
  </div>

</div>
</body>
</html>
HTML;
    }
}
