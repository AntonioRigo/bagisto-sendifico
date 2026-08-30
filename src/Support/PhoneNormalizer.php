<?php

namespace Tapstar\Sendifico\Support;

class PhoneNormalizer
{
    /**
     * Ecuador's international calling code.
     *
     * @var string
     */
    public const CALLING_CODE = '593';

    /**
     * Normalize a phone number to E.164 for Ecuador.
     *
     * A local trunk zero is dropped and an already-included country code is not doubled.
     * Returns null when no digits survive cleaning.
     *
     * @param  string|null  $rawPhone
     * @return string|null
     */
    public static function toE164($rawPhone)
    {
        $trimmed = trim((string) $rawPhone);

        if ($trimmed === '') {
            return null;
        }

        $hadPlus = str_starts_with($trimmed, '+');

        $digits = preg_replace('/\D/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($hadPlus) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (str_starts_with($digits, self::CALLING_CODE)) {
            return '+'.$digits;
        }

        return '+'.self::CALLING_CODE.$digits;
    }
}
