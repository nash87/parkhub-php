<?php

/**
 * Betriebsrat fairness & §87 BetrVG transparency module routes (api/v1).
 * Loaded only when MODULE_FAIRNESS=true.
 *
 * All endpoints are admin-only. A future slice will extend this with a
 * `works_council` role as an additional authorised principal.
 *
 * §87 differentiator: these endpoints provide the Betriebsrat with
 * aggregate-only, k-anonymised allocation metrics and a machine-readable
 * data-collection disclosure — satisfying the statutory co-determination
 * right under BetrVG §87 Abs. 1 Nr. 6 without exposing individual data.
 */

use App\Http\Controllers\Api\FairnessController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:fairness', 'auth:sanctum', 'throttle:api', 'admin'])
    ->group(function () {
        Route::get('/admin/fairness/report', [FairnessController::class, 'report']);
        Route::get('/admin/transparency/data-collection', [FairnessController::class, 'dataCollection']);
    });
