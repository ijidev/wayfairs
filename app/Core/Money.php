<?php

declare(strict_types=1);

namespace Wayfairs\Core;

final class Money
{
    public static function parse(string $value, int $scale): int
    {
        $value = trim($value);
        if ($scale < 0 || $scale > 18 || !preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]+))?$/', $value, $matches)) {
            throw new \InvalidArgumentException('Enter a valid positive amount.');
        }
        $fraction = $matches[2] ?? '';
        if (strlen($fraction) > $scale) {
            throw new \InvalidArgumentException('Amount has too many decimal places.');
        }
        $digits = ltrim($matches[1] . str_pad($fraction, $scale, '0'), '0');
        $digits = $digits === '' ? '0' : $digits;
        if (strlen($digits) > 18 || (strlen($digits) === 18 && strcmp($digits, (string) PHP_INT_MAX) > 0)) {
            throw new \InvalidArgumentException('Amount is too large.');
        }
        return (int) $digits;
    }

    public static function format(int|string $minor, int $scale, string $symbol = '', string $code = ''): string
    {
        $negative = (int) $minor < 0;
        $digits = ltrim((string) abs((int) $minor), '0');
        $digits = $digits === '' ? '0' : $digits;
        if ($scale > 0) {
            $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
            $value = substr($digits, 0, -$scale) . '.' . substr($digits, -$scale);
        } else {
            $value = $digits;
        }
        return ($negative ? '-' : '') . $symbol . $value . ($code !== '' ? ' ' . $code : '');
    }

    /**
     * Convert an integer minor-unit amount with a decimal rate into another
     * integer minor-unit amount without binary floating point.
     */
    public static function convert(int|string $minor, int $sourceScale, string $rateDecimal, int $targetScale = 2): int
    {
        if ($sourceScale < 0 || $sourceScale > 18 || $targetScale < 0 || $targetScale > 18) {
            throw new \InvalidArgumentException('Currency scale is invalid.');
        }
        $rateDecimal = trim($rateDecimal);
        if (!preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,18}))?$/', $rateDecimal, $matches)) {
            throw new \InvalidArgumentException('USD rate is invalid.');
        }
        $negative = (int) $minor < 0;
        $left = ltrim((string) abs((int) $minor), '0') ?: '0';
        $fraction = $matches[2] ?? '';
        $right = ltrim($matches[1] . $fraction, '0') ?: '0';
        $product = self::multiplyDigits($left, $right);
        $productScale = $sourceScale + strlen($fraction);
        $drop = $productScale - $targetScale;

        if ($drop > 0) {
            $product = str_pad($product, $drop + 1, '0', STR_PAD_LEFT);
            $cut = strlen($product) - $drop;
            $rounded = ltrim(substr($product, 0, $cut), '0') ?: '0';
            if ((int) $product[$cut] >= 5) {
                $rounded = self::incrementDigits($rounded);
            }
        } elseif ($drop < 0) {
            $rounded = $product . str_repeat('0', -$drop);
        } else {
            $rounded = $product;
        }

        $rounded = ltrim($rounded, '0') ?: '0';
        $limit = (string) PHP_INT_MAX;
        if (strlen($rounded) > strlen($limit) || (strlen($rounded) === strlen($limit) && strcmp($rounded, $limit) > 0)) {
            throw new \InvalidArgumentException('Converted amount is too large.');
        }
        $result = (int) $rounded;
        return $negative ? -$result : $result;
    }

    private static function multiplyDigits(string $left, string $right): string
    {
        if ($left === '0' || $right === '0') {
            return '0';
        }
        $result = array_fill(0, strlen($left) + strlen($right), 0);
        for ($i = strlen($left) - 1; $i >= 0; $i--) {
            for ($j = strlen($right) - 1; $j >= 0; $j--) {
                $position = $i + $j + 1;
                $sum = $result[$position] + ((int) $left[$i] * (int) $right[$j]);
                $result[$position] = $sum % 10;
                $result[$position - 1] += intdiv($sum, 10);
            }
        }
        return ltrim(implode('', $result), '0') ?: '0';
    }

    private static function incrementDigits(string $digits): string
    {
        $characters = str_split($digits);
        for ($index = count($characters) - 1; $index >= 0; $index--) {
            if ($characters[$index] !== '9') {
                $characters[$index] = (string) ((int) $characters[$index] + 1);
                return implode('', $characters);
            }
            $characters[$index] = '0';
        }
        return '1' . implode('', $characters);
    }
}
