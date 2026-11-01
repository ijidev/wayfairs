<?php

declare(strict_types=1);

/**
 * Builds one importable MySQL/MariaDB schema file from the numbered migrations.
 *
 * The output contains the migration-ledger table, every numbered MySQL migration
 * in order, and ledger rows whose checksums match the source files. The installer
 * then skips those migrations and only seeds settings, administrator, and assets.
 *
 *   php scripts/build-mysql-install.php > database/mysql/wayfairs-install.sql
 */

$root = dirname(__DIR__);
$directory = $root . '/database/mysql';
$files = glob($directory . '/[0-9][0-9][0-9]_*.sql') ?: [];
sort($files, SORT_STRING);
if ($files === []) {
    fwrite(STDERR, "No MySQL migrations found.\n");
    exit(1);
}

$out = [];
$out[] = '-- Wayfairs MySQL/MariaDB (InnoDB) schema, generated from database/mysql/NNN_*.sql.';
$out[] = '-- Do not edit by hand: run `php scripts/build-mysql-install.php`.';
$out[] = '--';
$out[] = '-- Import into an EMPTY database that you have already created with utf8mb4:';
$out[] = '--   CREATE DATABASE wayfairs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
$out[] = '--   mysql -u USER -p wayfairs < wayfairs-install.sql';
$out[] = '--';
$out[] = '-- Then open /install (or sign in if already installed) to create the administrator,';
$out[] = '-- site settings, and currency catalog. This file contains no customer, balance, or';
$out[] = '-- administrator data.';
$out[] = '';
$out[] = 'SET NAMES utf8mb4;';
$out[] = 'SET time_zone = \'+00:00\';';
$out[] = '';
$out[] = 'CREATE TABLE IF NOT EXISTS schema_migrations (';
$out[] = '    version VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,';
$out[] = '    checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,';
$out[] = '    applied_at DATETIME(6) NOT NULL';
$out[] = ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
$out[] = '';

$ledger = [];
foreach ($files as $file) {
    $version = pathinfo($file, PATHINFO_FILENAME);
    $sql = (string) file_get_contents($file);
    $ledger[] = [$version, hash('sha256', $sql)];
    $out[] = '-- ==== migration ' . $version . ' (source: ' . basename($file) . ') ====';
    $out[] = rtrim($sql);
    $out[] = '';
}

$out[] = '-- ==== migration ledger (matches the application checksums) ====';
$rows = [];
foreach ($ledger as [$version, $checksum]) {
    $rows[] = "    ('" . $version . "', '" . $checksum . "', UTC_TIMESTAMP(6))";
}
$out[] = 'INSERT INTO schema_migrations (version, checksum, applied_at) VALUES';
$out[] = implode(",\n", $rows) . ';';
$out[] = '';

echo implode("\n", $out);
