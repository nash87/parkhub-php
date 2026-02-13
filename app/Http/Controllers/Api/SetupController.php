<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SetupController extends Controller
{
    public function status()
    {
$completed = Setting::get("setup_completed", "false") === "true";        $hasAdmin = User::where("role", "admin")->orWhere("role", "superadmin")->exists();        return response()->json([            "setup_complete" => $completed,            "setup_completed" => $completed,            "has_admin" => $hasAdmin,            "has_parking_lots" => \App\Models\ParkingLot::count() > 0,            "has_users" => User::count() > 0,            "needs_password_change" => \App\Models\Setting::get("needs_password_change", "false") === "true",            "total_lots" => \App\Models\ParkingLot::count(),            "total_users" => User::count(),        ]);
    }

    public function init(Request $request)
    {
        if (Setting::get('setup_completed') === 'true') {
            return response()->json(['error' => 'Setup already completed'], 400);
        }

        $request->validate([
            'company_name' => 'required|string',
            'admin_username' => 'required|string|min:3',
            'admin_password' => 'required|string|min:8',
            'admin_email' => 'required|email',
            'admin_name' => 'required|string',
            'use_case' => 'nullable|string',
            'create_sample_data' => 'nullable|boolean',
        ]);

        // Create admin user
        $admin = User::create([
            'username' => $request->admin_username,
            'email' => $request->admin_email,
            'password' => Hash::make($request->admin_password),
            'name' => $request->admin_name,
            'role' => 'admin',
            'is_active' => true,
            'preferences' => ['language' => 'en', 'theme' => 'system', 'notifications_enabled' => true],
        ]);

        // Save settings
        Setting::set('setup_completed', 'true');
        Setting::set('company_name', $request->company_name);
        Setting::set('use_case', $request->use_case ?? 'corporate');
        Setting::set('self_registration', 'true');

        // Create sample data if requested
        if ($request->create_sample_data) {
            $lot = ParkingLot::create([
                'name' => 'Sample Parking Lot',
                'address' => 'Main Street 1',
                'total_slots' => 10,
                'available_slots' => 10,
                'status' => 'open',
            ]);

            for ($i = 1; $i <= 10; $i++) {
                ParkingSlot::create([
                    'lot_id' => $lot->id,
                    'slot_number' => 'A' . $i,
                    'status' => 'available',
                ]);
            }
        }

        $token = $admin->createToken('auth-token');

        return response()->json([
            'message' => 'Setup completed successfully',
            'user' => $admin,
            'tokens' => [
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(7)->toISOString(),
            ],
        ]);
    }
}
