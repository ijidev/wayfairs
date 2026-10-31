<?php

declare(strict_types=1);

namespace Wayfairs\Core;

final class Response
{
    public static function redirect(string $path, int $status = 303): never
    {
        header('Location: ' . Config::instance()->url($path), true, $status);
        exit;
    }

    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function notFound(): never
    {
        http_response_code(404);
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Not found</title><body><h1>Page not found</h1></body></html>';
        exit;
    }
}
