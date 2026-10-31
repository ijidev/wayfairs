<?php

declare(strict_types=1);

namespace Wayfairs\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Database
{
    private PDO $pdo;
    private string $driver;
    private bool $manualTransaction = false;

    public function __construct(array $configuration)
    {
        $this->driver = (string) ($configuration['driver'] ?? '');
        try {
            if ($this->driver === 'sqlite') {
                $path = (string) ($configuration['path'] ?? '');
                if ($path === '') {
                    throw new RuntimeException('SQLite path is missing.');
                }
                $directory = dirname($path);
                if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                    throw new RuntimeException('Could not create SQLite storage.');
                }
                @chmod($directory, 0700);
                $this->pdo = new PDO('sqlite:' . $path);
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                $this->pdo->exec('PRAGMA journal_mode = WAL');
                $this->pdo->exec('PRAGMA synchronous = FULL');
                $this->pdo->exec('PRAGMA busy_timeout = 5000');
                if (is_file($path)) {
                    @chmod($path, 0600);
                }
            } elseif ($this->driver === 'mysql') {
                $host = (string) ($configuration['host'] ?? '127.0.0.1');
                $port = (int) ($configuration['port'] ?? 3306);
                $database = (string) ($configuration['database'] ?? '');
                $charset = (string) ($configuration['charset'] ?? 'utf8mb4');
                if ($database === '') {
                    throw new RuntimeException('MySQL database name is missing.');
                }
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
                $this->pdo = new PDO($dsn, (string) ($configuration['username'] ?? ''), (string) ($configuration['password'] ?? ''));
                $this->pdo->exec("SET time_zone = '+00:00'");
                $this->pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            } else {
                throw new RuntimeException('Unsupported database driver.');
            }
        } catch (PDOException $error) {
            throw new RuntimeException('Database connection failed. Verify the supplied database details.', 0, $error);
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public static function fromConfig(?Config $config = null): self
    {
        $config ??= Config::instance();
        if (!$config->installed()) {
            throw new RuntimeException('Wayfairs is not installed.');
        }
        return new self((array) $config->get('database', []));
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function fetchOne(string $sql, array $parameters = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function execute(string $sql, array $parameters = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->rowCount();
    }

    public function transaction(callable $callback, bool $immediate = false): mixed
    {
        if ($this->pdo->inTransaction() || $this->manualTransaction) {
            return $callback($this);
        }
        $manual = false;
        try {
            if ($this->driver === 'sqlite' && $immediate) {
                $this->pdo->exec('BEGIN IMMEDIATE');
                $this->manualTransaction = true;
                $manual = true;
            } else {
                $this->pdo->beginTransaction();
            }
            $result = $callback($this);
            if ($manual) {
                $this->pdo->exec('COMMIT');
                $this->manualTransaction = false;
            } else {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $error) {
            if ($manual && $this->manualTransaction) {
                try {
                    $this->pdo->exec('ROLLBACK');
                } catch (Throwable) {
                    // Preserve the original failure.
                }
                $this->manualTransaction = false;
            } elseif ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function lockSuffix(): string
    {
        return $this->driver === 'mysql' ? ' FOR UPDATE' : '';
    }
}
