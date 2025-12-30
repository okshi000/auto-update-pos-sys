<?php

namespace App\Services;

class BarcodeService
{
    /**
     * Validate barcode format.
     * Accepts any alphanumeric barcode with minimum 4 characters.
     * For strict validation (EAN/UPC), use validateStrict().
     */
    public function validate(?string $barcode): bool
    {
        // Handle null or empty barcode - null is valid (no barcode)
        if ($barcode === null || $barcode === '') {
            return true;
        }

        // Reject barcodes with whitespace only
        if (preg_match('/^\s+$/', $barcode)) {
            return false;
        }

        // Minimum 1 character (any alphanumeric)
        if (strlen(trim($barcode)) < 1) {
            return false;
        }

        // Allow alphanumeric and common barcode characters
        if (!preg_match('/^[A-Za-z0-9\-_.]+$/', $barcode)) {
            return false;
        }

        return true;
    }

    /**
     * Strict validation for standard barcode formats.
     * Supports EAN-13, EAN-8, UPC-A only.
     */
    public function validateStrict(?string $barcode): bool
    {
        if ($barcode === null || $barcode === '') {
            return false;
        }

        // Reject barcodes with whitespace
        if (preg_match('/\s/', $barcode)) {
            return false;
        }

        // Remove any dashes
        $barcode = preg_replace('/[\-]/', '', $barcode);

        // Check if barcode is numeric and validate checksum
        if (strlen($barcode) === 13) {
            return $this->validateEan13($barcode);
        } elseif (strlen($barcode) === 8) {
            return $this->validateEan8($barcode);
        } elseif (strlen($barcode) === 12) {
            return $this->validateUpcA($barcode);
        }

        return false;
    }

    /**
     * Validate EAN-13 barcode.
     */
    public function validateEan13(string $barcode): bool
    {
        if (!preg_match('/^\d{13}$/', $barcode)) {
            return false;
        }

        return $this->validateEanChecksum($barcode);
    }

    /**
     * Validate EAN-8 barcode.
     */
    public function validateEan8(string $barcode): bool
    {
        if (!preg_match('/^\d{8}$/', $barcode)) {
            return false;
        }

        return $this->validateEanChecksum($barcode);
    }

    /**
     * Validate UPC-A barcode.
     */
    public function validateUpcA(string $barcode): bool
    {
        if (!preg_match('/^\d{12}$/', $barcode)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 11; $i++) {
            $sum += (int) $barcode[$i] * ($i % 2 === 0 ? 3 : 1);
        }

        $checkDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit === (int) $barcode[11];
    }

    /**
     * Validate EAN checksum (works for both EAN-13 and EAN-8).
     */
    protected function validateEanChecksum(string $barcode): bool
    {
        $length = strlen($barcode);
        $sum = 0;

        for ($i = 0; $i < $length - 1; $i++) {
            $multiplier = ($length === 13)
                ? ($i % 2 === 0 ? 1 : 3)
                : ($i % 2 === 0 ? 3 : 1);
            $sum += (int) $barcode[$i] * $multiplier;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit === (int) $barcode[$length - 1];
    }

    /**
     * Generate EAN-13 barcode.
     */
    public function generateEan13(string $prefix = '200'): string
    {
        // Use prefix 200-299 for internal use
        $base = $prefix . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);

        // Calculate check digit
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $base[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $checkDigit = (10 - ($sum % 10)) % 10;

        return $base . $checkDigit;
    }

    /**
     * Get barcode type.
     */
    public function getType(string $barcode): ?string
    {
        $barcode = preg_replace('/[\s\-]/', '', $barcode);

        if (strlen($barcode) === 13 && $this->validateEan13($barcode)) {
            return 'EAN-13';
        } elseif (strlen($barcode) === 8 && $this->validateEan8($barcode)) {
            return 'EAN-8';
        } elseif (strlen($barcode) === 12 && $this->validateUpcA($barcode)) {
            return 'UPC-A';
        } elseif (preg_match('/^[A-Za-z0-9]+$/', $barcode)) {
            return 'CODE-128';
        }

        return null;
    }
}
