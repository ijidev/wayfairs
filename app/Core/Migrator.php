<?php

declare(strict_types=1);

namespace Wayfairs\Core;

use RuntimeException;

final class Migrator
{
    public function __construct(private readonly Database $database)
    {
    }

    public function migrate(): array
    {
        $this->ensureMigrationTable();
        $directory = WAYFAIRS_ROOT . '/database/' . $this->database->driver();
        $files = glob($directory . '/[0-9][0-9][0-9]_*.sql') ?: [];
        sort($files, SORT_STRING);
        if ($files === []) {
            throw new RuntimeException('No database migrations were found.');
        }
        $applied = [];
        foreach ($files as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);
            $sql = (string) file_get_contents($file);
            $checksum = hash('sha256', $sql);
            $existing = $this->database->fetchOne('SELECT checksum FROM schema_migrations WHERE version = ?', [$version]);
            if ($existing) {
                if (!hash_equals((string) $existing['checksum'], $checksum)) {
                    throw new RuntimeException('Applied migration checksum changed: ' . $version);
                }
                continue;
            }
            $this->applySql($sql);
            $this->database->execute(
                'INSERT INTO schema_migrations (version, checksum, applied_at) VALUES (?, ?, ?)',
                [$version, $checksum, gmdate('Y-m-d H:i:s.u')]
            );
            $applied[] = $version;
        }
        return $applied;
    }

    private function ensureMigrationTable(): void
    {
        if ($this->database->driver() === 'mysql') {
            $this->database->pdo()->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
                checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                applied_at DATETIME(6) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            return;
        }
        $this->database->pdo()->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            version TEXT PRIMARY KEY,
            checksum TEXT NOT NULL,
            applied_at TEXT NOT NULL
        ) STRICT");
    }

    private function applySql(string $sql): void
    {
        if ($this->database->driver() === 'sqlite') {
            // SQLite implicitly commits transactions around DDL, so run the whole
            // migration file as one batch outside an explicit transaction.
            $this->database->pdo()->exec($sql);
            return;
        }
        foreach ($this->splitMysqlStatements($sql) as $statement) {
            $this->database->pdo()->exec($statement);
        }
    }

    private function splitMysqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $delimiter = ';';
        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            $trimmed = trim($line);
            if (str_starts_with(strtoupper($trimmed), 'DELIMITER ')) {
                $delimiter = trim(substr($trimmed, 10));
                continue;
            }
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $buffer .= $line . "\n";
            $candidate = rtrim($buffer);
            if ($delimiter !== '' && str_ends_with($candidate, $delimiter)) {
                $statement = trim(substr($candidate, 0, -strlen($delimiter)));
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            throw new RuntimeException('MySQL migration contains an unterminated statement.');
        }
        return $statements;
    }
}
