<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddFavoriteRequest;
use App\Http\Requests\AnonymizeAccountRequest;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Http\Resources\FavoriteResource;
use App\Http\Resources\NotificationResource;
use App\Models\Absence;
use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Services\User\UserAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct(private readonly UserAccountService $service) {}

    public function preferences(Request $request): JsonResponse
    {
        return response()->json($this->normalizePreferencesForApi($request->user()->preferences ?? []));
    }

    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $allowed = [
            'language', 'theme', 'notifications_enabled', 'email_notifications',
            'push', 'show_plate_in_calendar', 'default_lot_id',
            'locale', 'timezone',
        ];

        $user = $request->user();
        $prefs = array_merge(
            $this->normalizePreferencesForApi($user->preferences ?? []),
            $request->only($allowed)
        );
        $user->update(['preferences' => $prefs]);

        return response()->json($prefs);
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $now = now();

        // Use DB aggregate query instead of loading all bookings into memory
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $avgMinutes = (int) round((float) Booking::where('user_id', $userId)
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->selectRaw('AVG((julianday(end_time) - julianday(start_time)) * 1440) as avg_min')
                ->value('avg_min') ?? 0);
        } else {
            $avgMinutes = (int) round((float) Booking::where('user_id', $userId)
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_min')
                ->value('avg_min') ?? 0);
        }

        return response()->json([
            'total_bookings' => Booking::where('user_id', $userId)->count(),
            'bookings_this_month' => Booking::where('user_id', $userId)
                ->whereMonth('start_time', $now->month)
                ->whereYear('start_time', $now->year)->count(),
            'homeoffice_days_this_month' => Absence::where('user_id', $userId)
                ->where('absence_type', 'homeoffice')
                ->whereMonth('start_date', $now->month)
                ->whereYear('start_date', $now->year)->count(),
            'avg_duration_minutes' => $avgMinutes,
            'favorite_slot' => Booking::where('user_id', $userId)
                ->selectRaw('slot_number, COUNT(*) as cnt')
                ->groupBy('slot_number')
                ->orderByDesc('cnt')
                ->first()?->slot_number,
        ]);
    }

    public function credits(Request $request): JsonResponse
    {
        $user = $request->user();
        $creditsEnabled = Setting::get('credits_enabled', 'false') === 'true';

        return response()->json([
            'enabled' => $creditsEnabled,
            'balance' => $user->credits_balance,
            'monthly_quota' => $user->credits_monthly_quota,
            'last_refilled' => $user->credits_last_refilled,
            'transactions' => $user->creditTransactions()
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get(),
        ]);
    }

    public function favorites(Request $request): AnonymousResourceCollection
    {
        return FavoriteResource::collection(
            Favorite::where('user_id', $request->user()->id)->with('slot')->get()
        );
    }

    public function addFavorite(AddFavoriteRequest $request)
    {
        $fav = Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'slot_id' => $request->slot_id,
        ]);

        return FavoriteResource::make($fav)->response()->setStatusCode(201);
    }

    public function removeFavorite(Request $request, string $slotId): JsonResponse
    {
        Favorite::where('user_id', $request->user()->id)->where('slot_id', $slotId)->delete();

        return response()->json(['message' => 'Removed']);
    }

    public function notifications(Request $request): AnonymousResourceCollection
    {
        return NotificationResource::collection(
            Notification::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
        );
    }

    public function markNotificationRead(Request $request, string $id)
    {
        $notif = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notif->update(['read' => true]);

        return NotificationResource::make($notif);
    }

    // iCal export — bookings as calendar feed
    public function calendarExport(Request $request): Response
    {
        $ical = $this->service->buildIcalFeed($request->user());

        return response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="parkhub.ics"',
        ]);
    }

    // GDPR data export — everything about this user
    public function export(Request $request): JsonResponse
    {
        return response()->json($this->service->exportData($request->user()), 200, [
            'Content-Disposition' => 'attachment; filename="my-parkhub-data.json"',
        ]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)->update(['read' => true]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function pushUnsubscribe(Request $request): JsonResponse
    {
        PushSubscription::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Unsubscribed from push notifications']);
    }

    /**
     * GDPR Art. 17 — Right to Erasure.
     * Anonymizes all personal data while preserving anonymized booking records for audit/accounting.
     * Unlike deleteAccount() which CASCADE-deletes everything, this keeps booking records
     * with PII replaced by placeholder values (required for German tax law — 7-year retention).
     */
    public function anonymizeAccount(AnonymizeAccountRequest $request): JsonResponse
    {
        $user = $request->user();

        // Historical: `reason` defaults to "User request" when absent.
        // Keep the literal default inline here so Scramble can infer it
        // as the body default in docs/openapi/php.json — the previous
        // inline call lived inside an AuditLog::log() array literal,
        // which Scramble's AST walker also reads.
        $details = ['reason' => $request->input('reason', 'User request')];

        $ok = $this->service->anonymize(
            $user,
            (string) $request->input('password'),
            (string) $details['reason'],
            $request->ip(),
        );

        if (! $ok) {
            return response()->json(['error' => 'INVALID_PASSWORD', 'message' => 'Password confirmation failed'], 403);
        }

        // Build the response BEFORE invalidating tokens so the session is still valid when serialized
        $response = response()->json(['success' => true, 'data' => ['message' => 'Account deleted successfully'], 'error' => null, 'meta' => null], 200);

        // Invalidate all tokens AFTER building the response
        $user->tokens()->delete();

        return $response;
    }

    /**
     * Keep the public user preferences contract canonical while tolerating
     * legacy stored keys from older clients.
     *
     * @param  array<string, mixed>  $preferences
     * @return array<string, mixed>
     */
    private function normalizePreferencesForApi(array $preferences): array
    {
        if (array_key_exists('push_notifications', $preferences) && ! array_key_exists('push', $preferences)) {
            $preferences['push'] = $preferences['push_notifications'];
        }

        unset($preferences['push_notifications']);

        return $preferences;
    }
}
