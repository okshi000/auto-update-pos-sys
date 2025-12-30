<?php

namespace App\Helpers;

/**
 * Money formatting helper for Libyan Dinar (LYD).
 * Uses 3 decimal precision as standard for LYD.
 */
class MoneyHelper
{
    /**
     * Currency code for Libyan Dinar.
     */
    public const CURRENCY_CODE = 'LYD';

    /**
     * Decimal precision for LYD.
     */
    public const PRECISION = 3;

    /**
     * Format a monetary value with proper precision.
     *
     * @param float|int|string $amount The amount to format
     * @param bool $includeSymbol Whether to include currency symbol
     * @param string|null $locale Override locale (ar or en)
     * @return string
     */
    public static function format(
        float|int|string $amount,
        bool $includeSymbol = true,
        ?string $locale = null
    ): string {
        $amount = (float) $amount;
        $locale = $locale ?? app()->getLocale();

        // Format number with 3 decimal places
        $formatted = number_format($amount, self::PRECISION, '.', ',');

        if (!$includeSymbol) {
            return $formatted;
        }

        // RTL languages (Arabic) put currency after number
        if ($locale === 'ar') {
            return $formatted . ' د.ل';
        }

        return self::CURRENCY_CODE . ' ' . $formatted;
    }

    /**
     * Format for API responses (no symbol, just number).
     *
     * @param float|int|string $amount
     * @return float
     */
    public static function toFloat(float|int|string $amount): float
    {
        return round((float) $amount, self::PRECISION);
    }

    /**
     * Parse a formatted string back to float.
     *
     * @param string $value
     * @return float
     */
    public static function parse(string $value): float
    {
        // Remove currency symbols and spaces
        $cleaned = preg_replace('/[^\d.-]/', '', $value);
        return round((float) $cleaned, self::PRECISION);
    }

    /**
     * Calculate percentage with proper precision.
     *
     * @param float|int|string $amount
     * @param float|int|string $percentage
     * @return float
     */
    public static function percentage(
        float|int|string $amount,
        float|int|string $percentage
    ): float {
        return round((float) $amount * ((float) $percentage / 100), self::PRECISION);
    }

    /**
     * Add tax to an amount.
     *
     * @param float|int|string $amount
     * @param float|int|string $taxRate Percentage (e.g., 15 for 15%)
     * @return array{amount: float, tax: float, total: float}
     */
    public static function addTax(
        float|int|string $amount,
        float|int|string $taxRate
    ): array {
        $amount = (float) $amount;
        $tax = self::percentage($amount, $taxRate);
        $total = round($amount + $tax, self::PRECISION);

        return [
            'amount' => round($amount, self::PRECISION),
            'tax' => $tax,
            'total' => $total,
        ];
    }

    /**
     * Extract tax from a tax-inclusive amount.
     *
     * @param float|int|string $totalWithTax
     * @param float|int|string $taxRate Percentage
     * @return array{amount: float, tax: float, total: float}
     */
    public static function extractTax(
        float|int|string $totalWithTax,
        float|int|string $taxRate
    ): array {
        $total = (float) $totalWithTax;
        $taxRate = (float) $taxRate;

        $amount = round($total / (1 + ($taxRate / 100)), self::PRECISION);
        $tax = round($total - $amount, self::PRECISION);

        return [
            'amount' => $amount,
            'tax' => $tax,
            'total' => round($total, self::PRECISION),
        ];
    }

    /**
     * Get currency information for API responses.
     *
     * @return array
     */
    public static function getCurrencyInfo(): array
    {
        $locale = app()->getLocale();

        return [
            'code' => self::CURRENCY_CODE,
            'symbol' => $locale === 'ar' ? 'د.ل' : 'LYD',
            'name' => $locale === 'ar' ? 'دينار ليبي' : 'Libyan Dinar',
            'decimal_places' => self::PRECISION,
            'position' => $locale === 'ar' ? 'after' : 'before',
        ];
    }
}
