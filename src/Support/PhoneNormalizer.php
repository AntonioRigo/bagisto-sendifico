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
     * The trunk prefix an Ecuadorian number is written and dialled with.
     *
     * @var string
     */
    public const TRUNK_PREFIX = '0';

    /**
     * The prefix used to dial out of Ecuador, which some customers paste along.
     *
     * @var string
     */
    public const INTERNATIONAL_PREFIX = '00';

    /**
     * Digits of the longest Ecuadorian number once the trunk prefix is dropped.
     *
     * A mobile is nine (9XXXXXXXX) and a landline eight (area code plus seven), so
     * anything longer than this can only be carrying a country code.
     *
     * @var int
     */
    public const NATIONAL_DIGITS = 9;

    /**
     * Reduce a phone number to the national form Ecuadorian carriers accept.
     *
     * Some carriers reject a number that still carries +593, so the country code is
     * removed and the trunk zero people write locally is restored: (+593) 99 811 0137,
     * 593-998-110-137, 00593998110137 and 0998110137 all arrive as 0998110137. Working
     * from the digits rather than from a written prefix is what makes that hold however
     * the customer spaced, bracketed or punctuated the number. It is only ever rewritten
     * on its way to Sendifico — what the customer typed stays stored as is.
     *
     * A number too short to hold a country code is left alone, so the Guayaquil
     * landline 0593xxxxx keeps its digits instead of losing them to the prefix.
     *
     * @param  string|null  $rawPhone
     * @return string|null
     */
    public static function toNational($rawPhone)
    {
        $digits = preg_replace('/\D/', '', (string) $rawPhone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, self::INTERNATIONAL_PREFIX)) {
            $digits = substr($digits, strlen(self::INTERNATIONAL_PREFIX));
        }

        if (
            str_starts_with($digits, self::CALLING_CODE)
            && strlen($digits) > self::NATIONAL_DIGITS
        ) {
            $digits = substr($digits, strlen(self::CALLING_CODE));
        }

        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, self::TRUNK_PREFIX)
            ? $digits
            : self::TRUNK_PREFIX.$digits;
    }
}
