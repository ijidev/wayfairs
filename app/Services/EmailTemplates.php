<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use RuntimeException;
use Wayfairs\Core\Database;

final class EmailTemplates
{
    public const KEYS = ['registration', 'deposit', 'withdrawal', 'bank_transfer', 'bill_payment', 'topup', 'debit', 'otp', 'flight_request', 'account_status'];

    public function __construct(private readonly Database $database)
    {
    }

    public function all(): array
    {
        return $this->database->fetchAll('SELECT * FROM email_templates ORDER BY template_key ASC');
    }

    public function get(string $key): ?array
    {
        return $this->database->fetchOne('SELECT * FROM email_templates WHERE template_key = ?', [$key]);
    }

    public function update(string $key, string $subject, string $body, string $updatedBy): void
    {
        if (!in_array($key, self::KEYS, true)) {
            throw new RuntimeException('Unknown email template.');
        }
        if (trim($subject) === '' || trim($body) === '') {
            throw new RuntimeException('Subject and body are required.');
        }
        $existing = $this->database->fetchOne('SELECT id FROM email_templates WHERE template_key = ?', [$key]);
        if ($existing) {
            $this->database->execute('UPDATE email_templates SET subject = ?, body = ?, updated_by = ?, updated_at = ? WHERE template_key = ?', [mb_substr(trim($subject), 0, 200), trim($body), $updatedBy, gmdate('Y-m-d H:i:s.u'), $key]);
        } else {
            $this->database->execute('INSERT INTO email_templates (id, template_key, subject, body, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [\Wayfairs\Core\Security::uuid(), $key, mb_substr(trim($subject), 0, 200), trim($body), $updatedBy, gmdate('Y-m-d H:i:s.u')]);
        }
    }

    public function render(string $key, array $values): ?array
    {
        $row = $this->get($key);
        if ($row === null) {
            return null;
        }
        $subject = (string) $row['subject'];
        $body = (string) $row['body'];
        foreach ($values as $name => $value) {
            $placeholder = '{' . $name . '}';
            $subject = str_replace($placeholder, (string) $value, $subject);
            $body = str_replace($placeholder, (string) $value, $body);
        }
        return ['subject' => $subject, 'body' => $body];
    }
}