<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function live()
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready()
    {
        $dbStatus = 'ok';
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $dbStatus = 'error';
        }

        $version = is_file(base_path('VERSION')) ? trim(file_get_contents(base_path('VERSION'))) : '1.0.0-php';

        $status = $dbStatus === 'ok' ? 200 : 503;

        return response()->json([
            'status'   => $dbStatus === 'ok' ? 'ok' : 'degraded',
            'database' => $dbStatus,
            'version'  => $version,
        ], $status);
    }
}
