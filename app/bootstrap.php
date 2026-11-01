<?php

declare(strict_types=1);

if (!defined('WAYFAIRS_ROOT')) {
    define('WAYFAIRS_ROOT', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Wayfairs\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = WAYFAIRS_ROOT . '/app/' . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require WAYFAIRS_ROOT . '/app/helpers.php';

date_default_timezone_set('UTC');
ini_set('default_charset', 'UTF-8');

set_exception_handler(static function (Throwable $error): void {
    $requestId = $_SERVER['WAYFAIRS_REQUEST_ID'] ?? bin2hex(random_bytes(8));
    error_log(sprintf('[wayfairs] request=%s %s: %s in %s:%d', $requestId, $error::class, $error->getMessage(), $error->getFile(), $error->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Wayfairs · Error</title><body style="font:16px system-ui;background:#07120f;color:#eef8f2;padding:3rem">'
        . '<h1>We could not complete that request.</h1><p>Please retry or contact support with reference <code>'
        . e($requestId) . '</code>.</p></body></html>';
});
