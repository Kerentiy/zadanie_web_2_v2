<?php

declare(strict_types=1);

use Illuminate\Support\Env;

$basePath = dirname(__DIR__);

$path = (string) Env::get('LOG_PATH', 'storage/logs');
if (!str_starts_with($path, '/') && !str_starts_with($path, 'php://')) {
    $path = $basePath . '/' . $path;
}

return [
    'path' => rtrim($path, '/'),
    'level' => strtolower((string) Env::get('LOG_LEVEL', 'debug')),
    'max_files' => (int) Env::get('LOG_MAX_FILES', 14),
    'log_queries' => (bool) Env::get('LOG_QUERIES', false),

    'http' => [
        // Bodies longer than this are truncated in the log
        'body_max_bytes' => (int) Env::get('LOG_BODY_MAX_BYTES', 10240),
        // Header names (case-insensitive) whose values are replaced with ***
        'masked_headers' => ['authorization', 'cookie', 'set-cookie', 'x-api-key'],
        // JSON body keys (case-insensitive) whose values are replaced with ***
        'masked_fields' => ['password', 'password_confirmation', 'token', 'secret', 'api_key'],
    ],
];
