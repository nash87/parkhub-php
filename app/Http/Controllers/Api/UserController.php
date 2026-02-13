<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Absence;
use App\Models\Favorite;
use App\Models\Notification;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function preferences(Request $request)
    {
        return response()->json($request->user()->preferences ?? []);
    }

    public function updatePreferences(Request $request)
    {
        $user = $request->user();
        $prefs = array_merge($user->preferences ?? [], $request->all());
        $user->update(['preferences' => $prefs]);
        return response()->json($prefs);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        $now = now();

        return response()->json([
            'total_bookings' => Booking::where('user_id', $userId)->count(),
            'this_month' => Booking::where('user_id', $userId)
                ->whereMonth('start_time', $now->month)
                ->whereYear('start_time', $now->year)->count(),
            'homeoffice_days' => Absence::where('user_id', $userId)
                ->where('absence_type', 'homeoffice')->count(),
            'favorite_slot' => Booking::where('user_id', $userId)
                ->selectRaw('slot_number, COUNT(*) as cnt')
                ->groupBy('slot_number')
                ->orderByDesc('cnt')
                ->first()?->slot_number,
        ]);
    }

    public function favorites(Request $request)
    {
        return response()->json(
            Favorite::where('user_id', $request->user()->id)->with('slot')->get()
        );
    }

    public function addFavorite(Request $request)
    {
        $request->validate(['slot_id' => 'required|uuid']);
        $fav = Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'slot_id' => $request->slot_id,
        ]);
        return response()->json($fav, 201);
    }

    public function removeFavorite(Request $request, string $slotId)
    {
        Favorite::where('user_id', $request->user()->id)->where('slot_id', $slotId)->delete();
        return response()->json(['message' => 'Removed']);
    }

    public function notifications(Request $request)
    {
        return response()->json(
            Notification::where('user_id', $request->user()->id)->orderBy('created_at', 'desc')->limit(50)->get()
        );
    }

    public function markNotificationRead(Request $request, string $id)
    {
        $notif = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notif->update(['read' => true]);
        return response()->json($notif);
    }
}
