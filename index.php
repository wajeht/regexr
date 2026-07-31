<?php
/*
 * Stateless bootstrap for self-hosted RegExr.
 * Copyright (C) 2026 Jaw
 * SPDX-License-Identifier: GPL-3.0-only
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');

$html = file_get_contents(__DIR__ . '/index.html');
$open = '<script id="phpinject">';
$start = strpos($html, $open);
$end = $start === false ? false : strpos($html, '</script>', $start);

if ($start === false || $end === false) {
    http_response_code(500);
    echo 'Unable to initialize RegExr.';
    exit;
}

$start += strlen($open);
$config = json_encode([
    'PCREVersion' => PCRE_VERSION,
    'PHPVersion' => PHP_VERSION,
], JSON_THROW_ON_ERROR);
$init = "\nregexr.init(null, {}, {$config});\n";

echo substr($html, 0, $start) . $init . substr($html, $end);
