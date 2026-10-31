<?php

declare(strict_types=1);

namespace Wayfairs\Core;

final class Config
{
    private static ?self $instance = null;
    private array $values;

    private function __construct()
    {
        $file = WAYFAIRS_ROOT . '/storage/config.php';
        $loaded = is_file($file) ? require $file : [];
        $this->values = is_array($loaded) ? $loaded : [];
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function installed(): bool
    {
        return ($this->values['installed'] ?? false) === true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public function all(): array
    {
        return $this->values;
    }

    public function basePath(): string
    {
        $environment = trim((string) (getenv('WAYFAIRS_BASE_PATH') ?: ''));
        $configured = trim((string) $this->get('base_path', ''));
        $base = $environment !== '' ? $environment : $configured;
        if ($base === '' || $base === '/') {
            return '';
        }
        return '/' . trim($base, '/');
    }

    public function url(string $path = ''): string
    {
        $base = $this->basePath();
        $clean = ltrim($path, '/');
        return ($base === '' ? '' : $base) . '/' . $clean;
    }

    public function cookiePath(): string
    {
        return ($this->basePath() ?: '') . '/';
    }

    public static function write(array $values): void
    {
        $storage = WAYFAIRS_ROOT . '/storage';
        if (!is_dir($storage) && !mkdir($storage, 0700, true) && !is_dir($storage)) {
            throw new \RuntimeException('Could not create the storage directory.');
        }
        @chmod($storage, 0700);
        $target = $storage . '/config.php';
        $temporary = $target . '.tmp-' . bin2hex(random_bytes(6));
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($values, true) . ";\n";
        if (file_put_contents($temporary, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Could not write the installation configuration.');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $target)) {
            @unlink($temporary);
            throw new \RuntimeException('Could not activate the installation configuration.');
        }
        @chmod($target, 0600);
        self::reset();
    }
}
