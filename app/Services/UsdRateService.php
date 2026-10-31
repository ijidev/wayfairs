<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use Wayfairs\Core\Database;

final class UsdRateService
{
    private const FIAT_TTL = 21600;
    private const CRYPTO_TTL = 600;

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Return a code-keyed projection. Provider failures never manufacture a
     * value: a previously observed row is marked stale, otherwise unavailable.
     */
    public function ratesForAssets(array $assets, bool $force = false): array
    {
        $rates = [];
        foreach ($assets as $asset) {
            $code = strtoupper((string) ($asset['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            if ($code === 'USD') {
                $rates[$code] = [
                    'status' => 'current',
                    'rate' => '1',
                    'provider' => 'base',
                    'provider_label' => 'USD base currency',
                    'provider_reference' => 'USD/USD',
                    'source_updated_at' => null,
                    'fetched_at' => null,
                ];
                continue;
            }

            $cached = $this->database->fetchOne('SELECT * FROM usd_rates WHERE asset_id = ?', [(string) $asset['id']]);
            $ttl = (string) ($asset['asset_type'] ?? '') === 'crypto' ? self::CRYPTO_TTL : self::FIAT_TTL;
            if (!$force && $cached !== null && $this->age((string) $cached['fetched_at']) <= $ttl) {
                $rates[$code] = $this->project($cached, 'current');
                continue;
            }

            try {
                $observed = (string) ($asset['asset_type'] ?? '') === 'crypto'
                    ? $this->fetchCrypto($asset)
                    : $this->fetchFiat($asset);
                $this->persist((string) $asset['id'], $observed);
                $rates[$code] = $this->project($observed, 'current');
            } catch (\RuntimeException) {
                $rates[$code] = $cached !== null
                    ? $this->project($cached, 'stale')
                    : [
                        'status' => 'unavailable',
                        'rate' => null,
                        'provider' => (string) ($asset['asset_type'] ?? '') === 'crypto' ? 'coingecko' : 'frankfurter',
                        'provider_label' => (string) ($asset['asset_type'] ?? '') === 'crypto' ? 'CoinGecko' : 'Frankfurter',
                        'provider_reference' => (string) ($asset['usd_provider_reference'] ?? $code),
                        'source_updated_at' => null,
                        'fetched_at' => null,
                    ];
            }
        }
        return $rates;
    }

    private function fetchFiat(array $asset): array
    {
        $code = strtoupper((string) ($asset['code'] ?? ''));
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            throw new \RuntimeException('Fiat currency code is not supported by the rate adapter.');
        }
        $body = $this->request('https://api.frankfurter.dev/v2/rate/' . strtolower($code) . '/usd');
        if (!preg_match('/"rate"\s*:\s*([0-9]+(?:\.[0-9]+)?)/', $body, $rateMatch)) {
            throw new \RuntimeException('Frankfurter response did not contain a rate.');
        }
        preg_match('/"date"\s*:\s*"([0-9]{4}-[0-9]{2}-[0-9]{2})"/', $body, $dateMatch);
        return [
            'rate_decimal' => $this->normalizeRate($rateMatch[1]),
            'provider' => 'frankfurter',
            'provider_reference' => $code . '/USD',
            'source_updated_at' => isset($dateMatch[1]) ? $dateMatch[1] . ' 00:00:00' : null,
            'fetched_at' => $this->now(),
        ];
    }

    private function fetchCrypto(array $asset): array
    {
        $reference = strtolower(trim((string) ($asset['usd_provider_reference'] ?? '')));
        if (!preg_match('/^[a-z0-9-]{1,120}$/', $reference)) {
            throw new \RuntimeException('Crypto asset has no valid CoinGecko reference.');
        }
        $url = 'https://api.coingecko.com/api/v3/simple/price?ids=' . rawurlencode($reference)
            . '&vs_currencies=usd&include_last_updated_at=true&precision=8';
        $headers = [];
        $key = trim((string) (getenv('WAYFAIRS_COINGECKO_API_KEY') ?: ''));
        if ($key !== '') {
            $headers[] = 'x-cg-demo-api-key: ' . $key;
        }
        $body = $this->request($url, $headers);
        $objectPattern = '/"' . preg_quote($reference, '/') . '"\s*:\s*\{([^{}]*)\}/';
        if (!preg_match($objectPattern, $body, $objectMatch)
            || !preg_match('/"usd"\s*:\s*([0-9]+(?:\.[0-9]+)?)/', $objectMatch[1], $rateMatch)) {
            throw new \RuntimeException('CoinGecko response did not contain a USD price.');
        }
        $source = null;
        if (preg_match('/"last_updated_at"\s*:\s*([0-9]{9,12})/', $objectMatch[1], $updatedMatch)) {
            $source = gmdate('Y-m-d H:i:s', (int) $updatedMatch[1]);
        }
        return [
            'rate_decimal' => $this->normalizeRate($rateMatch[1]),
            'provider' => 'coingecko',
            'provider_reference' => $reference,
            'source_updated_at' => $source,
            'fetched_at' => $this->now(),
        ];
    }

    private function request(string $url, array $headers = []): string
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL is unavailable.');
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Rate provider request could not start.');
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'Wayfairs/0.1 USD-rate-cache',
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $type = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        curl_close($handle);
        if (!is_string($body) || $status !== 200 || strlen($body) > 1048576 || !str_contains(strtolower($type), 'json')) {
            throw new \RuntimeException('Rate provider is unavailable.');
        }
        return $body;
    }

    private function persist(string $assetId, array $rate): void
    {
        $existing = $this->database->fetchOne('SELECT asset_id FROM usd_rates WHERE asset_id = ?', [$assetId]);
        $values = [
            (string) $rate['rate_decimal'],
            (string) $rate['provider'],
            (string) ($rate['provider_reference'] ?? '') ?: null,
            $rate['source_updated_at'] ?? null,
            (string) $rate['fetched_at'],
        ];
        if ($existing !== null) {
            $this->database->execute('UPDATE usd_rates SET rate_decimal = ?, provider = ?, provider_reference = ?, source_updated_at = ?, fetched_at = ? WHERE asset_id = ?', [...$values, $assetId]);
            return;
        }
        try {
            $this->database->execute('INSERT INTO usd_rates (rate_decimal, provider, provider_reference, source_updated_at, fetched_at, asset_id) VALUES (?, ?, ?, ?, ?, ?)', [...$values, $assetId]);
        } catch (\Throwable $error) {
            if ($this->database->fetchOne('SELECT asset_id FROM usd_rates WHERE asset_id = ?', [$assetId]) === null) {
                throw $error;
            }
            $this->database->execute('UPDATE usd_rates SET rate_decimal = ?, provider = ?, provider_reference = ?, source_updated_at = ?, fetched_at = ? WHERE asset_id = ?', [...$values, $assetId]);
        }
    }

    private function project(array $row, string $status): array
    {
        $provider = (string) ($row['provider'] ?? '');
        return [
            'status' => $status,
            'rate' => (string) ($row['rate_decimal'] ?? ''),
            'provider' => $provider,
            'provider_label' => match ($provider) {
                'frankfurter' => 'Frankfurter',
                'coingecko' => 'CoinGecko',
                default => 'USD base currency',
            },
            'provider_reference' => $row['provider_reference'] ?? null,
            'source_updated_at' => $row['source_updated_at'] ?? null,
            'fetched_at' => $row['fetched_at'] ?? null,
        ];
    }

    private function normalizeRate(string $rate): string
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,17})(?:\.([0-9]{1,18}))?$/', $rate, $matches)) {
            throw new \RuntimeException('Rate provider returned an invalid value.');
        }
        $fraction = rtrim((string) ($matches[2] ?? ''), '0');
        $normalized = $matches[1] . ($fraction !== '' ? '.' . $fraction : '');
        if ($normalized === '0') {
            throw new \RuntimeException('Rate provider returned zero.');
        }
        return $normalized;
    }

    private function age(string $timestamp): int
    {
        $time = strtotime($timestamp);
        return $time === false ? PHP_INT_MAX : max(0, time() - $time);
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s.u');
    }
}
