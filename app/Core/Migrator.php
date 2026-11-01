<?php

declare(strict_types=1);

namespace Wayfairs\Core;

use RuntimeException;

final class Migrator
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Applies pending migrations. Safe to call on every request: it returns at
     * once when nothing is pending, and a file lock stops concurrent requests
     * from applying the same migration twice.
     */
    public function migrate(): array
    {
        $this->ensureMigrationTable();
        if ($this->pendingFiles() === []) {
            return [];
        }
        return $this->withLock(function (): array {
            $applied = [];
            foreach ($this->pendingFiles() as $file) {
                $version = pathinfo($file, PATHINFO_FILENAME);
                $sql = (string) file_get_contents($file);
                $this->applySql($sql);
                $this->database->execute(
                    'INSERT INTO schema_migrations (version, checksum, applied_at) VALUES (?, ?, ?)',
                    [$version, hash('sha256', $sql), gmdate('Y-m-d H:i:s.u')]
                );
                $applied[] = $version;
            }
            return $applied;
        });
    }

    private function pendingFiles(): array
    {
        $pending = [];
        foreach ($this->migrationFiles() as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);
            $checksum = hash('sha256', (string) file_get_contents($file));
            $existing = $this->database->fetchOne('SELECT checksum FROM schema_migrations WHERE version = ?', [$version]);
            if ($existing) {
                if (!hash_equals((string) $existing['checksum'], $checksum)) {
                    throw new RuntimeException('Applied migration checksum changed: ' . $version);
                }
                continue;
            }
            $pending[] = $file;
        }
        return $pending;
    }

    private function migrationFiles(): array
    {
        $directory = WAYFAIRS_ROOT . '/database/' . $this->database->driver();
        $files = glob($directory . '/[0-9][0-9][0-9]_*.sql') ?: [];
        sort($files, SORT_STRING);
        if ($files === []) {
            throw new RuntimeException('No database migrations were found.');
        }
        return $files;
    }

    private function withLock(callable $callback): array
    {
        $directory = WAYFAIRS_ROOT . '/storage';
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $handle = fopen($directory . '/migrate.lock', 'c');
        if ($handle === false) {
            throw new RuntimeException('Could not open the database migration lock.');
        }
        flock($handle, LOCK_EX);
        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
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
        // Statements run one at a time so that a migration interrupted part-way
        // can be finished by the next run: DDL is not transactional on MySQL and
        // SQLite cannot roll back a batch once it has partly run.
        foreach ($this->splitStatements($sql) as $statement) {
            $statement = $this->skipAlreadyApplied($statement);
            if ($statement !== null) {
                $this->database->pdo()->exec($statement);
            }
        }
    }

    /**
     * Returns the statement with any part that already exists removed, or null
     * when nothing is left to do. Only ADD COLUMN and CREATE TABLE are repaired;
     * other statements must be idempotent on their own.
     */
    private function skipAlreadyApplied(string $statement): ?string
    {
        if (preg_match('/^ALTER\s+TABLE\s+(\w+)\s+(.+)$/is', $statement, $match) === 1) {
            $table = $match[1];
            $clauses = preg_split('/,\s*(?=ADD\s+COLUMN\s)/i', trim($match[2])) ?: [trim($match[2])];
            $kept = [];
            foreach ($clauses as $clause) {
                $clause = trim($clause);
                if (preg_match('/^ADD\s+COLUMN\s+(\w+)/i', $clause, $column) === 1 && $this->columnExists($table, $column[1])) {
                    continue;
                }
                $kept[] = $clause;
            }
            if ($kept === []) {
                return null;
            }
            return 'ALTER TABLE ' . $table . ' ' . implode(', ', $kept);
        }
        if (preg_match('/^CREATE\s+TABLE\s+(\w+)/i', $statement, $match) === 1 && $this->tableExists($match[1])) {
            return null;
        }
        if (preg_match('/^CREATE\s+(?:UNIQUE\s+)?INDEX\s+(\w+)/i', $statement, $match) === 1 && $this->indexExists($match[1])) {
            return null;
        }
        return $statement;
    }

    private function columnExists(string $table, string $column): bool
    {
        if ($this->database->driver() === 'mysql') {
            $row = $this->database->fetchOne(
                'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );
            return (int) ($row['total'] ?? 0) > 0;
        }
        foreach ($this->database->fetchAll('PRAGMA table_info(' . $table . ')') as $row) {
            if (strcasecmp((string) $row['name'], $column) === 0) {
                return true;
            }
        }
        return false;
    }

    private function indexExists(string $index): bool
    {
        if ($this->database->driver() === 'mysql') {
            $row = $this->database->fetchOne(
                'SELECT COUNT(*) AS total FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME = ?',
                [$index]
            );
            return (int) ($row['total'] ?? 0) > 0;
        }
        return $this->database->fetchOne("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [$index]) !== null;
    }

    private function tableExists(string $table): bool
    {
        if ($this->database->driver() === 'mysql') {
            $row = $this->database->fetchOne(
                'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );
            return (int) ($row['total'] ?? 0) > 0;
        }
        return $this->database->fetchOne("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]) !== null;
    }

    private function splitStatements(string $sql): array
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
            // Trigger bodies contain semicolons; keep them whole until their END;.
            $openTrigger = preg_match('/^CREATE\s+TRIGGER\b/i', $candidate) === 1 && preg_match('/\bEND;$/i', $candidate) !== 1;
            if (!$openTrigger && $delimiter !== '' && str_ends_with($candidate, $delimiter)) {
                $statement = trim(substr($candidate, 0, -strlen($delimiter)));
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            throw new RuntimeException('Migration contains an unterminated statement.');
        }
        return $statements;
    }
}
