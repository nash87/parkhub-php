<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response('OK', 200)->header('Content-Type', 'text/plain');
});

// SPA fallback - serve index.html for all non-API, non-file routes
Route::get('/{any}', function () {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return response()->file($indexPath);
    }
    return response('ParkHub PHP Edition - Frontend not built yet. Run: cd resources/js && npm install && npm run build', 200);
})->where('any', '^(?!api|health|sanctum|storage).*$');
