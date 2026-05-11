<?php

declare(strict_types=1);

$publicRoot = realpath(__DIR__.'/../public');

if ($publicRoot === false) {
    http_response_code(500);
    echo 'public root missing';

    return true;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$decodedPath = rawurldecode($path);
$candidate = realpath($publicRoot.$decodedPath);

function parkhub_e2e_content_type(string $extension): string
{
    return match ($extension) {
        'css' => 'text/css; charset=UTF-8',
        'js', 'mjs' => 'application/javascript; charset=UTF-8',
        'json', 'map' => 'application/json; charset=UTF-8',
        'html', 'htm' => 'text/html; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        default => 'application/octet-stream',
    };
}

function parkhub_e2e_is_public_asset(string $extension): bool
{
    return in_array($extension, [
        'avif',
        'css',
        'eot',
        'gif',
        'ico',
        'jpeg',
        'jpg',
        'js',
        'map',
        'mjs',
        'png',
        'svg',
        'ttf',
        'webp',
        'woff',
        'woff2',
    ], true);
}

if (
    $candidate !== false
    && is_file($candidate)
    && str_starts_with($candidate, $publicRoot.DIRECTORY_SEPARATOR)
) {
    $extension = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));

    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Embedder-Policy: credentialless');
    header('Cross-Origin-Resource-Policy: '.(parkhub_e2e_is_public_asset($extension) ? 'cross-origin' : 'same-origin'));
    header('Content-Type: '.parkhub_e2e_content_type($extension));

    if (str_starts_with($decodedPath, '/_astro/')) {
        header('Cache-Control: public, max-age=31536000, immutable');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        readfile($candidate);
    }

    return true;
}

require $publicRoot.'/index.php';

return true;
