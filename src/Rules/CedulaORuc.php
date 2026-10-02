<?php

namespace Tapstar\Sendifico\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CedulaORuc implements ValidationRule
{
    /**
     * Validate an Ecuadorian cédula (10 digits) or RUC (13 digits), including
     * the SRI check digit for all three RUC classes: natural person (módulo
     * 10), public entity (third digit 6, módulo 11) and private company (third
     * digit 9, módulo 11). The Registro Civil also issues cédulas whose third
     * digit is 6, so a 13 digit number starting with one of those is read as a
     * natural person when it fails the public entity check.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        $valid = match (strlen($digits)) {
            10 => static::isValidCedula($digits),
            13 => static::isValidRuc($digits),
            default => false,
        };

        if (! $valid) {
            $fail('sendifico::app.shop.invalid-vat-id')->translate();
        }
    }

    /**
     * Whether the ten digits form a valid cédula.
     */
    protected static function isValidCedula(string $digits): bool
    {
        $province = (int) substr($digits, 0, 2);

        if (
            ($province < 1 || $province > 24)
            && $province !== 30
        ) {
            return false;
        }

        if ((int) $digits[2] > 6) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $product = (int) $digits[$i] * ($i % 2 === 0 ? 2 : 1);

            $sum += $product > 9 ? $product - 9 : $product;
        }

        return (10 - $sum % 10) % 10 === (int) $digits[9];
    }

    /**
     * Whether the thirteen digits form a valid RUC.
     */
    protected static function isValidRuc(string $digits): bool
    {
        $third = (int) $digits[2];

        if ($third <= 5) {
            return static::isValidCedula(substr($digits, 0, 10))
                && substr($digits, 10) !== '000';
        }

        if ($third === 6) {
            if (
                static::passesModulo11($digits, [3, 2, 7, 6, 5, 4, 3, 2], 8)
                && substr($digits, 9) !== '0000'
            ) {
                return true;
            }

            return static::isValidCedula(substr($digits, 0, 10))
                && substr($digits, 10) !== '000';
        }

        if ($third === 9) {
            return static::passesModulo11($digits, [4, 3, 2, 7, 6, 5, 4, 3, 2], 9)
                && substr($digits, 10) !== '000';
        }

        return false;
    }

    /**
     * Verify a módulo 11 check digit at the given position.
     */
    protected static function passesModulo11(string $digits, array $coefficients, int $checkPosition): bool
    {
        $sum = 0;

        foreach ($coefficients as $index => $coefficient) {
            $sum += $coefficient * (int) $digits[$index];
        }

        $remainder = $sum % 11;

        $check = $remainder === 0 ? 0 : 11 - $remainder;

        if ($check === 10) {
            return false;
        }

        return $check === (int) $digits[$checkPosition];
    }
}
