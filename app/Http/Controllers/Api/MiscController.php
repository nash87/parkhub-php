<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Models\Webhook;
use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Http\Request;

class MiscController extends Controller
{
    // Push
    public function pushSubscribe(Request $request)
    {
        $request->validate(['endpoint' => 'required', 'p256dh' => 'required', 'auth' => 'required']);
        $sub = PushSubscription::updateOrCreate(
            ['user_id' => $request->user()->id, 'endpoint' => $request->endpoint],
            ['p256dh' => $request->p256dh, 'auth' => $request->auth]
        );
        return response()->json($sub, 201);
    }

    // Email settings — admin only (contains SMTP credentials)
    public function emailSettings(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
        return response()->json([
            'smtp_host'    => Setting::get('smtp_host'),
            'smtp_port'    => Setting::get('smtp_port', '587'),
            'smtp_user'    => Setting::get('smtp_user'),
            'smtp_from'    => Setting::get('smtp_from'),
            'smtp_enabled' => Setting::get('smtp_enabled', 'false'),
            // Note: smtp_password is intentionally omitted from GET responses
        ]);
    }

    public function updateEmailSettings(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
        $request->validate([
            'smtp_host'     => 'sometimes|string|max:255',
            'smtp_port'     => 'sometimes|integer|between:1,65535',
            'smtp_user'     => 'sometimes|string|max:255',
            'smtp_password' => 'sometimes|string|max:255',
            'smtp_from'     => 'sometimes|email|max:255',
            'smtp_enabled'  => 'sometimes|boolean',
        ]);
        foreach (['smtp_host', 'smtp_port', 'smtp_user', 'smtp_password', 'smtp_from', 'smtp_enabled'] as $key) {
            if ($request->has($key)) Setting::set($key, $request->$key);
        }
        return response()->json(['message' => 'Email settings updated']);
    }

    // QR Code
    public function qrCode(string $bookingId)
    {
        $booking = Booking::findOrFail($bookingId);
        $data = json_encode([
            'booking_id' => $booking->id,
            'slot' => $booking->slot_number,
            'lot' => $booking->lot_name,
            'valid_until' => $booking->end_time?->toISOString(),
        ]);

        // Return QR data (frontend generates the visual QR)
        return response()->json(['qr_data' => $data, 'booking' => $booking]);
    }

    // Webhooks — admin only (webhook URLs + secrets are sensitive config)
    public function webhooks(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
        return response()->json(Webhook::all());
    }

    public function createWebhook(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
        $request->validate([
            'url'    => 'required|url|max:2048',
            'events' => 'nullable|array',
            'secret' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
        ]);
        $webhook = Webhook::create($request->only(['url', 'events', 'secret', 'active']));
        return response()->json($webhook, 201);
    }

    public function updateWebhook(Request $request, string $id)
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
        $request->validate([
            'url'    => 'sometimes|url|max:2048',
            'events' => 'nullable|array',
            'secret' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
        ]);
        $webhook = Webhook::findOrFail($id);
        $webhook->update($request->only(['url', 'events', 'secret', 'active']));
        return response()->json($webhook);
    }

    public function deleteWebhook(Request $request, string $id)
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
        Webhook::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
