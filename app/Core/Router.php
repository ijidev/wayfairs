<?php

declare(strict_types=1);

namespace Wayfairs\Core;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, string $access = 'public', bool $sensitive = false): void
    {
        $this->routes[] = compact('method', 'pattern', 'handler', 'access', 'sensitive');
    }

    public function get(string $pattern, callable $handler, string $access = 'public'): void
    {
        $this->add('GET', $pattern, $handler, $access);
    }

    public function post(string $pattern, callable $handler, string $access = 'public', bool $sensitive = false): void
    {
        $this->add('POST', $pattern, $handler, $access, $sensitive);
    }

    public function match(Request $request): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }
            $names = [];
            $regex = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', static function (array $match) use (&$names): string {
                $names[] = $match[1];
                return '([^/]+)';
            }, $route['pattern']);
            if (!is_string($regex) || !preg_match('#^' . $regex . '$#', $request->path(), $matches)) {
                continue;
            }
            array_shift($matches);
            $parameters = [];
            foreach ($names as $index => $name) {
                $parameters[$name] = rawurldecode((string) ($matches[$index] ?? ''));
            }
            return $route + ['parameters' => $parameters];
        }
        return null;
    }
}
