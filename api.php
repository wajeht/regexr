<?php
/*
 * Stateless PHP/PCRE solver derived from gskinner/regexr's regex/solve action.
 * Upstream copyright (C) 2017 gskinner.com, inc.
 * Modified 2026-07-31 for the self-hosted image.
 * SPDX-License-Identifier: GPL-3.0-only
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

ini_set('pcre.backtrack_limit', '1000000');
ini_set('pcre.recursion_limit', '100000');
set_time_limit(3);

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function unicodeLength(string $value): int
{
    $count = preg_match_all('/./us', $value, $unused);
    return $count === false ? strlen($value) : $count;
}

function charOffset(string $text, int $byteOffset): int
{
    if ($byteOffset < 0) {
        return -1;
    }
    return unicodeLength(substr($text, 0, $byteOffset));
}

function regexError(): ?array
{
    $code = preg_last_error();
    if ($code === PREG_NO_ERROR) {
        return null;
    }

    $id = in_array($code, [PREG_BACKTRACK_LIMIT_ERROR, PREG_RECURSION_LIMIT_ERROR, PREG_JIT_STACKLIMIT_ERROR], true)
        ? 'infinite'
        : ($code === PREG_BAD_UTF8_ERROR || $code === PREG_BAD_UTF8_OFFSET_ERROR ? 'badutf8' : 'error');

    return [
        'message' => preg_last_error_msg(),
        'name' => 'PCRE_ERROR_' . $code,
        'id' => $id,
    ];
}

function matchEntry(array $match, string $text): array
{
    $first = array_shift($match);
    $result = [
        'i' => charOffset($text, (int) $first[1]),
        'l' => unicodeLength((string) $first[0]),
        'groups' => [],
    ];

    foreach ($match as $key => $group) {
        if (is_int($key)) {
            $result['groups'][] = [
                'i' => charOffset($text, (int) $group[1]),
                'l' => unicodeLength((string) $group[0]),
            ];
        }
    }

    return $result;
}

function runMatch(string $regex, string $text, bool $global): array
{
    $matches = [];
    $result = $global
        ? preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)
        : preg_match($regex, $text, $matches, PREG_OFFSET_CAPTURE);

    if ($result === false || $result === 0) {
        return [];
    }

    if ($global) {
        return array_map(static fn(array $match): array => matchEntry($match, $text), $matches);
    }

    return [matchEntry($matches, $text)];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(['success' => false, 'data' => ['error' => 'POST required']], 405);
}

if (($_POST['action'] ?? '') !== 'regex/solve') {
    respond(['success' => false, 'data' => ['error' => 'Unsupported action']], 404);
}

try {
    $request = json_decode($_POST['data'] ?? '', false, 64, JSON_THROW_ON_ERROR);
    if (!is_object($request) || !isset($request->pattern)) {
        throw new InvalidArgumentException('Invalid request');
    }

    $pattern = (string) $request->pattern;
    $flags = (string) ($request->flags ?? '');
    $global = str_contains($flags, 'g');
    $modifiers = str_replace(['g', 'y'], '', $flags);
    if (preg_match('/^[imsxuADUJnSr]*$/', $modifiers) !== 1) {
        throw new InvalidArgumentException('Unsupported PCRE modifier');
    }

    $regex = '/' . $pattern . '/' . $modifiers;
    $mode = (string) ($request->mode ?? 'text');
    $id = $request->id ?? null;
    $started = microtime(true);

    if ($mode === 'tests') {
        $results = [];
        foreach (($request->tests ?? []) as $test) {
            $matches = [];
            $matched = $global
                ? preg_match_all($regex, (string) $test->text, $matches)
                : preg_match($regex, (string) $test->text, $matches);
            $entry = ['id' => $test->id];
            if ($matched === false) {
                $entry['error'] = regexError();
            } elseif ($matched > 0) {
                preg_match($regex, (string) $test->text, $first, PREG_OFFSET_CAPTURE);
                $entry['i'] = charOffset((string) $test->text, (int) $first[0][1]);
                $entry['l'] = unicodeLength((string) $first[0][0]);
            }
            $results[] = $entry;
        }
        $data = ['id' => $id, 'timestamp' => time(), 'mode' => 'tests', 'matches' => $results];
    } else {
        $text = (string) ($request->text ?? '');
        $tool = is_object($request->tool ?? null) ? $request->tool : (object) ['id' => '', 'input' => ''];
        $toolResult = '';
        if (($tool->id ?? '') === 'replace') {
            $toolResult = preg_replace($regex, (string) ($tool->input ?? ''), $text) ?? '';
        } elseif (($tool->id ?? '') === 'list') {
            $values = [];
            preg_replace_callback($regex, static function (array $matches) use (&$values, $regex, $tool): string {
                $values[] = preg_replace($regex, (string) ($tool->input ?? ''), $matches[0]) ?? '';
                return $matches[0];
            }, $text);
            $toolResult = implode('', $values);
        }

        $data = [
            'id' => $id,
            'timestamp' => time(),
            'mode' => 'text',
            'matches' => runMatch($regex, $text, $global),
            'tool' => ['id' => $tool->id ?? '', 'result' => $toolResult],
        ];
        $error = regexError();
        if ($error !== null) {
            $data['error'] = $error;
        }
    }

    $data['time'] = round((microtime(true) - $started) * 1000, 4);
    respond(['success' => true, 'data' => $data]);
} catch (Throwable $error) {
    respond(['success' => false, 'data' => ['error' => $error->getMessage()]], 400);
}
