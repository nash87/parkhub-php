<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ParkingSlotResource;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\GuestBooking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    private function requireAdmin($request): void
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
    }

    public function users(Request $request): JsonResponse
    {
        $this->requireAdmin($request);
        $perPage = min((int) request('per_page', 20), 100);
        $users = User::paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'error' => null,
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function updateUser(Request $request, string $id)
    {
        $this->requireAdmin($request);
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,'.$id,
            'role' => 'sometimes|in:user,admin,superadmin',
            'is_active' => 'sometimes|boolean',
            'department' => 'sometimes|nullable|string|max:255',
            'password' => 'sometimes|string|min:8',
        ]);
        $user = User::findOrFail($id);
        $data = $request->only(['name', 'email', 'is_active', 'department']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }
        $user->update($data);
        // Handle role separately — it's excluded from $fillable to prevent mass-assignment escalation
        if ($request->filled('role')) {
            $user->role = $request->role;
            $user->save();
        }

        // Return via toArray() to respect $hidden
        return UserResource::make($user->fresh());
    }

    public function deleteUser(Request $request, string $id): JsonResponse
    {
        $this->requireAdmin($request);
        if ($id === $request->user()->id) {
            return response()->json(['error' => 'Cannot delete your own account'], 400);
        }
        User::findOrFail($id)->delete();

        return response()->json(['message' => 'User deleted']);
    }

    public function importUsers(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $request->validate([
            'users' => 'required|array|max:500',
            'users.*.username' => 'required|string|min:3|max:50|alpha_dash',
            'users.*.email' => 'required|email|max:255',
            'users.*.name' => 'nullable|string|max:255',
            'users.*.role' => 'nullable|in:user,admin',
            'users.*.department' => 'nullable|string|max:255',
            'users.*.password' => 'nullable|string|min:8|max:128',
        ]);

        $imported = 0;

        foreach ($request->users as $userData) {
            if (User::where('username', $userData['username'])->orWhere('email', $userData['email'])->exists()) {
                continue;
            }
            $user = User::create([
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password'] ?? Str::random(16)),
                'name' => $userData['name'] ?? $userData['username'],
                'is_active' => true,
                'department' => $userData['department'] ?? null,
                'preferences' => ['language' => 'en', 'theme' => 'system'],
            ]);
            $user->role = $userData['role'] ?? 'user';
            $user->save();
            $imported++;
        }

        return response()->json(['imported' => $imported]);
    }

    public function bookings(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $query = Booking::with('user')->orderBy('start_time', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->has('lot_name') && $request->lot_name !== 'all') {
            $query->where('lot_name', $request->lot_name);
        }
        if ($request->has('from_date')) {
            $query->where('start_time', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('end_time', '<=', $request->to_date.' 23:59:59');
        }

        $perPage = min((int) $request->get('per_page', 100), 500);

        return response()->json($query->paginate($perPage));
    }

    public function cancelBooking(Request $request, string $id)
    {
        $this->requireAdmin($request);

        $booking = Booking::findOrFail($id);
        $booking->update(['status' => Booking::STATUS_CANCELLED]);

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'admin_booking_cancelled',
            'details' => ['booking_id' => $id],
        ]);

        return BookingResource::make($booking->fresh());
    }

    // ── Guest Bookings ────────────────────────────────────────────────────────

    public function guestBookings(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $query = GuestBooking::with(['lot', 'slot', 'creator'])
            ->orderBy('start_time', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->has('from_date')) {
            $query->where('start_time', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('end_time', '<=', $request->to_date.' 23:59:59');
        }

        $perPage = min((int) $request->get('per_page', 50), 200);
        $paginated = $query->paginate($perPage);

        $guests = collect($paginated->items())->map(function ($g) {
            return [
                'id' => $g->id,
                'guest_name' => $g->guest_name,
                'guest_code' => $g->guest_code,
                'lot_id' => $g->lot_id,
                'lot_name' => $g->lot?->name ?? '-',
                'slot_id' => $g->slot_id,
                'slot_number' => $g->slot?->number ?? '-',
                'start_time' => $g->start_time,
                'end_time' => $g->end_time,
                'vehicle_plate' => $g->vehicle_plate,
                'status' => $g->status,
                'created_by' => $g->created_by,
                'created_by_name' => $g->creator?->name ?? '-',
                'created_at' => $g->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $guests,
            'error' => null,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    public function cancelGuestBooking(Request $request, string $id): JsonResponse
    {
        $this->requireAdmin($request);

        $guest = GuestBooking::findOrFail($id);
        $guest->update(['status' => 'cancelled']);

        // Also cancel the associated regular booking
        Booking::where('lot_id', $guest->lot_id)
            ->where('slot_id', $guest->slot_id)
            ->where('start_time', $guest->start_time)
            ->where('end_time', $guest->end_time)
            ->whereIn('status', ['confirmed', 'active'])
            ->update(['status' => Booking::STATUS_CANCELLED]);

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'admin_guest_booking_cancelled',
            'details' => ['guest_booking_id' => $id, 'guest_name' => $guest->guest_name],
        ]);

        return response()->json([
            'success' => true,
            'data' => $guest->fresh(),
            'error' => null,
        ]);
    }

    public function auditLog(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $query = AuditLog::orderBy('created_at', 'desc');

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('search')) {
            $search = addcslashes($request->search, '%_\\');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', '%'.$search.'%')
                    ->orWhere('action', 'like', '%'.$search.'%');
            });
        }

        return response()->json($query->paginate($request->get('per_page', 50)));
    }

    public function updateSlot(Request $request, string $id)
    {
        $this->requireAdmin($request);
        $request->validate([
            'slot_number' => 'sometimes|string|max:20',
            'status' => 'sometimes|in:available,occupied,reserved,maintenance',
            'reserved_for_department' => 'sometimes|nullable|string|max:255',
            'zone_id' => 'sometimes|nullable|uuid|exists:zones,id',
        ]);
        $slot = ParkingSlot::findOrFail($id);
        $slot->update($request->only(['slot_number', 'status', 'reserved_for_department', 'zone_id']));

        return ParkingSlotResource::make($slot->fresh());
    }

    public function deleteLot(Request $request, string $id): JsonResponse
    {
        $this->requireAdmin($request);
        $lot = ParkingLot::findOrFail($id);
        $lot->delete();

        return response()->json(['message' => 'Lot deleted']);
    }

    public function updatesCheck(): JsonResponse
    {
        return response()->json(['update_available' => false, 'current_version' => '1.3.0-php']);
    }

    public function features(): JsonResponse
    {
        $available = [
            ['id' => 'micro_animations', 'name' => 'Micro Animations', 'description' => 'Subtle hover/tap animations'],
            ['id' => 'credits', 'name' => 'Credits System', 'description' => 'Credit-based booking'],
        ];

        return response()->json(['success' => true, 'data' => ['enabled' => ['micro_animations', 'credits'], 'available' => $available], 'error' => null, 'meta' => null]);
    }

    public function updateFeatures(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['enabled' => $request->input('enabled', [])], 'error' => null, 'meta' => null]);
    }
}
