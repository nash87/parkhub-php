<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Build Label
    |--------------------------------------------------------------------------
    |
    | Lightweight runtime/build identifier surfaced by the public health and
    | version endpoints. Keep this config-backed so cached config does not
    | break static analysis or runtime reporting.
    |
    */

    'build' => env('PARKHUB_BUILD', 'php-laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HSTS (HTTP Strict Transport Security)
    |--------------------------------------------------------------------------
    |
    | Enable to add the Strict-Transport-Security header to all responses.
    | Only enable when TLS termination is confirmed (reverse proxy or PaaS).
    | Default: false (safe for local dev and HTTP-only setups).
    |
    */

    'hsts' => (bool) env('APP_HSTS', false),

    /*
    |--------------------------------------------------------------------------
    | Metrics Token
    |--------------------------------------------------------------------------
    |
    | Optional bearer token for protecting the /api/metrics endpoint.
    | When set, requests must include Authorization: Bearer <token>.
    | Leave empty to allow unauthenticated access (NOT recommended in production).
    |
    */

    'metrics_token' => env('METRICS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Disable Rate Limits (E2E only)
    |--------------------------------------------------------------------------
    |
    | When true, every named RateLimiter is raised to an effectively
    | infinite budget so a full Playwright suite — which funnels every
    | test through loginViaUi() from the same localhost IP — doesn't
    | cascade into 429s. NEVER set this in production.
    |
    */

    'disable_rate_limits' => filter_var(
        env('PARKHUB_DISABLE_RATE_LIMITS', false),
        FILTER_VALIDATE_BOOLEAN,
    ),

    /*
    |--------------------------------------------------------------------------
    | VAPID Subject
    |--------------------------------------------------------------------------
    |
    | The contact URI (mailto: or https:) for Web Push VAPID authentication.
    | Must be a valid URL. Used as the "subject" claim in VAPID tokens.
    |
    */

    'vapid_subject' => env('VAPID_SUBJECT', 'mailto:admin@parkhub.test'),

];
