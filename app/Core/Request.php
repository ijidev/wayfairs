<?php

declare(strict_types=1);

namespace Wayfairs\Core;

final class Request
{
    private string $path;
    private string $requestId;
    private array $routeParameters = [];

    public function __construct(private readonly Config $config)
    {
        $raw = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = $config->basePath();
        if ($base !== '' && ($raw === $base || str_starts_with($raw, $base . '/'))) {
            $raw = substr($raw, strlen($base)) ?: '/';
        }
        $this->path = '/' . ltrim($raw, '/');
        $incoming = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
        $this->requestId = preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/', $incoming) ? $incoming : Security::uuid();
        $_SERVER['WAYFAIRS_REQUEST_ID'] = $this->requestId;
    }

    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        return rtrim($this->path, '/') ?: '/';
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function setRouteParameters(array $parameters): void
    {
        $this->routeParameters = $parameters;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParameters[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function allPost(): array
    {
        return is_array($_POST) ? $_POST : [];
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
