<?php
/*
 * Self-hosted RegExr runtime router.
 * Copyright (C) 2026 Jaw
 * SPDX-License-Identifier: GPL-3.0-only
 */

declare(strict_types=1);

header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), geolocation=(), microphone=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self'; worker-src 'self' blob:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/healthz') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "ok\n";
    return true;
}

if ($path === '/server/api.php') {
    require __DIR__ . '/server/api.php';
    return true;
}

$file = realpath(__DIR__ . $path);
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$publicExtensions = ['css', 'gif', 'ico', 'js', 'json', 'png', 'svg', 'txt', 'webmanifest', 'webp'];
if ($file !== false
    && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR)
    && is_file($file)
    && in_array($extension, $publicExtensions, true)) {
    return false;
}

if ($path === '/' || $path === '/index.html' || preg_match('#^/[a-z0-9]+/?$#i', $path) === 1) {
    require __DIR__ . '/index.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not Found\n";
return true;
