<?php

// Build allowed origins: always include APP_URL, local dev, plus any extras from env
$baseOrigins = array_filter([
    env('APP_URL'),                   // The actual deployed URL (e.g. https://parkhub-php-demo.onrender.com)
    'https://parkhub-php.test',       // Local K8s dev
    'http://localhost',
    'http://localhost:5173',          // Vite dev server
    'http://127.0.0.1',
    'http://127.0.0.1:5173',
]);

// Additional origins from env (comma-separated, e.g. "https://myapp.com,https://staging.myapp.com")
$extraOrigins = env('APP_EXTRA_ALLOWED_ORIGINS')
    ? array_map('trim', explode(',', env('APP_EXTRA_ALLOWED_ORIGINS')))
    : [];

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique(array_merge($baseOrigins, $extraOrigins))),
    'allowed_origins_patterns' => array_filter([
        // Auto-allow subdomains on common free hosting platforms
        '^https://[\w-]+\.onrender\.com$',
        '^https://[\w-]+\.railway\.app$',
        '^https://[\w-]+\.fly\.dev$',
        '^https://[\w-]+\.koyeb\.app$',
        // Allow demos page on GitHub Pages
        '^https://nash87\.github\.io$',
    ]),
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => false,
];
