<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function version(Request $request)
    {
        return response()->json([
            'version' => '1.0.0-php',
            'php_version' => PHP_VERSION,
            'build' => 'php-laravel',
        ]);
    }

    public function maintenance(Request $request)
    {
        $active = Setting::get('maintenance_mode', 'false') === 'true';
        return response()->json([
            'active' => $active,
            'message' => Setting::get('maintenance_message', ''),
        ]);
    }
}
