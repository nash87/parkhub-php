<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Recommendation engine contract
    |--------------------------------------------------------------------------
    |
    | weighted_v1 is the shared deterministic ParkHub scoring contract used by
    | the Rust and PHP stacks. Future fop-pipeline adapters must introduce a new
    | algorithm version rather than changing weighted_v1 semantics in place.
    |
    */

    'algorithm' => env('RECOMMENDATION_ALGORITHM', 'weighted_v1'),

    'weights' => [
        'frequency' => (float) env('RECOMMENDATION_WEIGHT_FREQUENCY', 40.0),
        'preferred_lot' => (float) env('RECOMMENDATION_WEIGHT_PREFERRED_LOT', 20.0),
        'availability' => (float) env('RECOMMENDATION_WEIGHT_AVAILABILITY', 30.0),
        'price' => (float) env('RECOMMENDATION_WEIGHT_PRICE', 20.0),
        'distance' => (float) env('RECOMMENDATION_WEIGHT_DISTANCE', 10.0),
        'accessibility_bonus' => (float) env('RECOMMENDATION_WEIGHT_ACCESSIBILITY_BONUS', 0.0),
        'feature_bonus' => (float) env('RECOMMENDATION_WEIGHT_FEATURE_BONUS', 2.0),
    ],

    'max_results' => (int) env('RECOMMENDATION_MAX_RESULTS', 5),
    'explain' => true,
    'profile_safe_mode' => true,

    'pipeline' => [
        'endpoint' => env('RECOMMENDATION_PIPELINE_ENDPOINT'),
        'pipeline_name' => env('RECOMMENDATION_PIPELINE_NAME', 'parkhub-recommendations'),
        'timeout_ms' => (int) env('RECOMMENDATION_PIPELINE_TIMEOUT_MS', 750),
        'fallback_enabled' => true,
    ],
];
