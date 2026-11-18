<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use Wayfairs\Core\Database;
use Wayfairs\Core\Security;

/**
 * Handles customer proof-of-payment slips for deposit requests.
 *
 * Files live under storage/uploads, outside the public document root. Storage
 * names are random UUIDs, never the customer's filename or a deposit reference,
 * so a download cannot be guessed and a path cannot be traversed.
 */
final class DepositReceiptService
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    private const TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    ];

    public function __construct(private readonly Database $database, private readonly string $root = WAYFAIRS_ROOT)
    {
    }

    public function captureUpload(mixed $file): array
    {
        if (!is_array($file)) {
            throw new \RuntimeException('Upload a receipt slip as proof of payment.');
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Upload a receipt slip as proof of payment.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('The receipt could not be uploaded. Please try again with a smaller file.');
        }
        $temporary = (string) ($file['tmp_name'] ?? '');
        if ($temporary === '' || !is_uploaded_file($temporary)) {
            throw new \RuntimeException('The receipt could not be read. Please upload it again.');
        }
        return $this->capturePath($temporary, (string) ($file['name'] ?? ''));
    }

    /**
     * Validate a local file and stage it. `captureUpload()` is the HTTP entry
     * point; this method also lets the file path be tested from the CLI.
     */
    public function capturePath(string $source, string $originalName): array
    {
        if (!is_file($source)) {
            throw new \RuntimeException('The receipt could not be read. Please upload it again.');
        }
        $size = filesize($source);
        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            throw new \RuntimeException('The receipt must be a file under 5 MB.');
        }
        $mime = (string) ((new \finfo(FILEINFO_MIME_TYPE))->file($source) ?: '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$extension]) || self::TYPES[$extension] !== $mime) {
            throw new \RuntimeException('Receipts must be a JPG, PNG, WebP, or PDF file.');
        }
        $this->ensureDirectories();
        $stagedName = Security::uuid() . '.' . $extension;
        $stagedPath = $this->stagingDirectory() . '/' . $stagedName;
        if (!copy($source, $stagedPath)) {
            throw new \RuntimeException('The receipt could not be saved. Please try again.');
        }
        return [
            'staged_name' => $stagedName,
            'name' => $this->safeName($originalName),
            'mime' => $mime,
            'size' => $size,
        ];
    }

    /** Move a staged receipt into permanent storage and link it to the deposit. */
    public function attach(string $depositId, array $receipt): void
    {
        $stagedName = (string) ($receipt['staged_name'] ?? '');
        $stagedPath = $this->stagingDirectory() . '/' . $stagedName;
        if ($stagedName === '' || !is_file($stagedPath)) {
            throw new \RuntimeException('The receipt could not be saved. Please upload it again.');
        }
        $deposit = $this->database->fetchOne('SELECT receipt_file FROM deposit_requests WHERE id = ?', [$depositId]);
        if (!$deposit) {
            $this->discard($receipt);
            throw new \RuntimeException('The deposit request could not be found.');
        }
        if ($deposit['receipt_file'] !== null) {
            // Replayed submission with the same idempotency token; keep the
            // first receipt and drop the duplicate staging file.
            $this->discard($receipt);
            return;
        }
        $this->ensureDirectories();
        $finalPath = $this->directory() . '/' . $stagedName;
        if (!rename($stagedPath, $finalPath)) {
            throw new \RuntimeException('The receipt could not be saved. Please try again.');
        }
        try {
            $changed = $this->database->execute(
                'UPDATE deposit_requests SET receipt_file = ?, receipt_name = ?, receipt_mime = ?, receipt_size = ?, receipt_uploaded_at = ? WHERE id = ? AND receipt_file IS NULL',
                [
                    $stagedName,
                    (string) ($receipt['name'] ?? 'receipt'),
                    (string) ($receipt['mime'] ?? ''),
                    (int) ($receipt['size'] ?? 0),
                    gmdate('Y-m-d H:i:s.u'),
                    $depositId,
                ]
            );
        } catch (\Throwable $error) {
            @unlink($finalPath);
            throw $error;
        }
        if ($changed === 0) {
            @unlink($finalPath);
        }
    }

    public function discard(array $receipt): void
    {
        $stagedName = (string) ($receipt['staged_name'] ?? '');
        if ($stagedName === '') {
            return;
        }
        $path = $this->stagingDirectory() . '/' . $stagedName;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Load the receipt and its metadata for an administrator download. */
    public function forAdmin(string $depositId): array
    {
        $deposit = $this->database->fetchOne(
            'SELECT d.*, u.email, u.first_name, u.last_name FROM deposit_requests d JOIN users u ON u.id = d.user_id WHERE d.id = ?',
            [$depositId]
        );
        if (!$deposit || $deposit['receipt_file'] === null) {
            throw new \RuntimeException('No receipt slip is attached to this deposit.');
        }
        $path = $this->resolveStoredName((string) $deposit['receipt_file']);
        if (!is_file($path)) {
            throw new \RuntimeException('No receipt slip is attached to this deposit.');
        }
        return [
            'deposit' => $deposit,
            'path' => $path,
            'mime' => (string) ($deposit['receipt_mime'] ?? 'application/octet-stream'),
            'name' => (string) ($deposit['receipt_name'] ?? 'receipt'),
        ];
    }

    /** Reject anything that is not one of our own generated storage names. */
    public function resolveStoredName(string $name): string
    {
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.(jpg|jpeg|png|webp|pdf)$/', $name)) {
            throw new \RuntimeException('Invalid receipt reference.');
        }
        $directory = realpath($this->directory());
        $path = realpath($this->directory() . '/' . $name);
        if ($directory === false || $path === false || !str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Invalid receipt reference.');
        }
        return $path;
    }

    private function directory(): string
    {
        return $this->root . '/storage/uploads/deposit-receipts';
    }

    private function stagingDirectory(): string
    {
        return $this->directory() . '/.staging';
    }

    private function ensureDirectories(): void
    {
        foreach ([$this->directory(), $this->stagingDirectory()] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('The receipt storage directory is not available.');
            }
        }
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        $name = trim($name);
        return $name !== '' ? mb_substr($name, 0, 120) : 'receipt';
    }
}
