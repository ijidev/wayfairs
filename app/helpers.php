<?php

declare(strict_types=1);

use Wayfairs\Core\Config;
use Wayfairs\Core\Database;
use Wayfairs\Core\Money;
use Wayfairs\Core\Security;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function wayfairs_url(string $path = ''): string
{
    return Config::instance()->url($path);
}

function wayfairs_current_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $base = Config::instance()->basePath();
    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = substr($path, strlen($base)) ?: '/';
    }
    return rtrim('/' . ltrim($path, '/'), '/') ?: '/';
}

function wayfairs_nav_active(string $path): bool
{
    $current = wayfairs_current_path();
    if ($current === $path) {
        return true;
    }
    if ($path === '/dashboard' && str_starts_with($current, '/accounts/')) {
        return true;
    }
    if ($path === '/transactions' && str_starts_with($current, '/receipts/')) {
        return true;
    }
    return $path !== '/admin' && str_starts_with($current, rtrim($path, '/') . '/');
}

function wayfairs_icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'overview' => '<path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>',
        'deposit' => '<path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/>',
        'withdraw' => '<path d="M12 21V9"/><path d="M7 14l5-5 5 5"/><path d="M5 3h14"/>',
        'transfer' => '<path d="M7 7h11l-3-3"/><path d="M17 17H6l3 3"/><path d="M18 7l-3 3"/><path d="M6 17l3-3"/>',
        'invoice' => '<path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 16h6"/>',
        'verification' => '<path d="M12 3l7 3v5c0 4.6-2.8 8.1-7 10-4.2-1.9-7-5.4-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
        'security' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3M12 14v3"/>',
        'customers' => '<path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0015 19.4a1.7 1.7 0 00-1 .6 1.7 1.7 0 00-.4 1.1V21h-4v-.1A1.7 1.7 0 008.6 19.4a1.7 1.7 0 00-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 004.6 15a1.7 1.7 0 00-.6-1 1.7 1.7 0 00-1.1-.4H3v-4h.1A1.7 1.7 0 004.6 8.6a1.7 1.7 0 00-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 009 4.6a1.7 1.7 0 001-.6 1.7 1.7 0 00.4-1.1V3h4v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0019.4 9c.16.37.37.71.6 1 .3.3.7.47 1.1.47h.1v4h-.1A1.7 1.7 0 0019.4 15z"/>',
        'audit' => '<path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
        'wallet' => '<path d="M4 6h14a2 2 0 012 2v11H4a2 2 0 01-2-2V5a2 2 0 012-2h13"/><path d="M15 11h7v5h-7a2.5 2.5 0 010-5z"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'check' => '<path d="M5 12l4 4L19 6"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',
        'layers' => '<path d="M12 2l9 5-9 5-9-5z"/><path d="M3 12l9 5 9-5M3 17l9 5 9-5"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'support' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.7 2.7 0 115 1.4c-.8.7-1.8 1.1-2.2 2.1M12 17h.01"/>',
        'activity' => '<path d="M3 12h4l2.5-7 5 14 2.5-7h4"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'building' => '<path d="M4 21h16M6 21V8l6-4 6 4v13M9 11h1M14 11h1M9 15h1M14 15h1"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'copy' => '<rect x="8" y="8" width="11" height="11" rx="2"/><path d="M16 8V5a2 2 0 00-2-2H5a2 2 0 00-2 2v9a2 2 0 002 2h3"/>',
    ];
    $path = $paths[$name] ?? $paths['overview'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function wayfairs_csrf(): string
{
    return (string) ($_SESSION['csrf_token'] ?? '');
}

function wayfairs_idem(): string
{
    return Security::token(16);
}

function wayfairs_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('M j, Y · H:i') . ' UTC';
    } catch (Throwable) {
        return '—';
    }
}

function wayfairs_amount(int|string $minor, int $scale, string $symbol = '', string $code = ''): string
{
    $plain = Money::format($minor, $scale, '', '');
    $negative = str_starts_with($plain, '-');
    $plain = ltrim($plain, '-');
    [$integer, $fraction] = array_pad(explode('.', $plain, 2), 2, '');
    $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $integer) ?? $integer;
    return ($negative ? '-' : '') . $symbol . $integer . ($fraction !== '' ? '.' . $fraction : '') . ($code !== '' ? ' ' . $code : '');
}

/**
 * Render a minor-unit integer as a plain major-unit decimal for editable form
 * inputs (no thousands separators, no symbol).
 */
function wayfairs_major(int|string $minor, int $scale): string
{
    return Money::format($minor, $scale, '', '');
}

function wayfairs_usd_minor(int|string $minor, int $assetScale, ?array $rate): ?int
{
    if (!is_array($rate) || empty($rate['rate']) || ($rate['status'] ?? 'unavailable') === 'unavailable') {
        return null;
    }
    try {
        return Money::convert($minor, $assetScale, (string) $rate['rate'], 2);
    } catch (Throwable) {
        return null;
    }
}

function wayfairs_usd_equivalent(int|string $minor, int $assetScale, ?array $rate): string
{
    $usdMinor = wayfairs_usd_minor($minor, $assetScale, $rate);
    return $usdMinor === null ? 'USD rate unavailable' : wayfairs_amount($usdMinor, 2, '$', 'USD');
}

function wayfairs_rate_disclosure(?array $rate): string
{
    if (!is_array($rate) || ($rate['status'] ?? 'unavailable') === 'unavailable') {
        return 'Live USD rate unavailable';
    }
    $label = (string) ($rate['provider_label'] ?? 'Observed provider');
    $status = ($rate['status'] ?? '') === 'stale' ? 'stale cached rate' : 'observed rate';
    $at = wayfairs_datetime($rate['source_updated_at'] ?? $rate['fetched_at'] ?? null);
    return $label . ' · ' . $status . ($at !== '—' ? ' · ' . $at : '');
}

function wayfairs_countries(): array
{
    static $countries = null;
    if ($countries !== null) {
        return $countries;
    }
    $countries = [];
    if (class_exists(ResourceBundle::class)) {
        $bundle = ResourceBundle::create('en', 'ICUDATA-region');
        $regions = $bundle instanceof ResourceBundle ? $bundle->get('Countries') : null;
        if ($regions instanceof ResourceBundle) {
            foreach ($regions as $code => $name) {
                if (preg_match('/^[A-Z]{2}$/', (string) $code) && !in_array((string) $code, ['EU', 'EZ', 'QO', 'UN', 'XA', 'XB', 'ZZ'], true) && is_string($name) && $name !== 'Unknown Region') {
                    $countries[(string) $code] = $name;
                }
            }
        }
    }
    asort($countries, SORT_NATURAL | SORT_FLAG_CASE);
    return $countries;
}

function wayfairs_pill(string $status): string
{
    $label = str_replace('_', ' ', $status);
    $tone = match (true) {
        in_array($status, ['completed', 'verified', 'active', 'paid', 'approved', 'current', 'sent', 'received'], true), str_starts_with($status, 'kyc verified') => 'good',
        in_array($status, ['pending', 'processing', 'in_review', 'reviewing', 'open', 'restricted', 'needs_resubmission', 'stale', 'kyc pending', 'kyc in_review', 'kyc needs_resubmission'], true) => 'warn',
        in_array($status, ['failed', 'rejected', 'blocked', 'closed', 'frozen', 'reversed', 'unavailable', 'kyc rejected', 'kyc expired'], true) => 'bad',
        default => '',
    };
    return '<span class="pill ' . e($tone) . '">' . e($label) . '</span>';
}

function wayfairs_public_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $config = Config::instance();
    if (!$config->installed()) {
        return $cache;
    }
    try {
        foreach (Database::fromConfig($config)->fetchAll('SELECT setting_key, setting_value FROM site_settings WHERE is_public = 1') as $row) {
            $cache[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
    } catch (Throwable) {
        $cache = [];
    }
    return $cache;
}

function wayfairs_set_session_cookie(string $token, int $ttlSeconds): void
{
    $config = Config::instance();
    setcookie((string) $config->get('session.cookie', 'wayfairs_session'), $token, [
        'expires' => time() + $ttlSeconds,
        'path' => $config->cookiePath(),
        'secure' => (bool) $config->get('session.secure', false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function wayfairs_clear_session_cookie(): void
{
    $config = Config::instance();
    setcookie((string) $config->get('session.cookie', 'wayfairs_session'), '', [
        'expires' => time() - 3600,
        'path' => $config->cookiePath(),
        'secure' => (bool) $config->get('session.secure', false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
