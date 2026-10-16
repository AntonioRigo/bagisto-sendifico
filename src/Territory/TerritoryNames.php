<?php

namespace Tapstar\Sendifico\Territory;

use Illuminate\Support\Str;

class TerritoryNames
{
    /**
     * The provinces as Ecuador writes them. The carrier's catalog keeps them in
     * capitals and without accents, which is no way to label an address.
     *
     * @var array<string, string>
     */
    public const PROVINCES = [
        'azuay' => 'Azuay',
        'bolivar' => 'Bolívar',
        'canar' => 'Cañar',
        'carchi' => 'Carchi',
        'chimborazo' => 'Chimborazo',
        'cotopaxi' => 'Cotopaxi',
        'el oro' => 'El Oro',
        'esmeraldas' => 'Esmeraldas',
        'galapagos' => 'Galápagos',
        'guayas' => 'Guayas',
        'imbabura' => 'Imbabura',
        'loja' => 'Loja',
        'los rios' => 'Los Ríos',
        'manabi' => 'Manabí',
        'morona santiago' => 'Morona Santiago',
        'napo' => 'Napo',
        'orellana' => 'Orellana',
        'pastaza' => 'Pastaza',
        'pichincha' => 'Pichincha',
        'santa elena' => 'Santa Elena',
        'santo domingo de los tsachilas' => 'Santo Domingo de los Tsáchilas',
        'sucumbios' => 'Sucumbíos',
        'tungurahua' => 'Tungurahua',
        'zamora chinchipe' => 'Zamora Chinchipe',
    ];

    /**
     * The words that join a place name, which Spanish keeps in lower case.
     *
     * @var array<int, string>
     */
    public const LINKERS = ['de', 'del', 'en', 'y'];

    /**
     * Articles, which stay lower case only where a linker put them there —
     * "San Miguel de los Bancos", but "Recinto La Maritza".
     *
     * @var array<int, string>
     */
    public const ARTICLES = ['el', 'la', 'las', 'los'];

    /**
     * A province as it should be read, falling back to tidying whatever the
     * catalog sent when it is one we do not know.
     */
    public static function province(string $name): string
    {
        return self::PROVINCES[TerritoryMatcher::normalize($name)] ?? self::place($name);
    }

    /**
     * A cantón as it should be read. The catalog spells cantones without
     * accents but often keeps them on the parroquia of the same name, so that
     * spelling is borrowed wherever it exists.
     */
    public static function canton(string $canton, ?string $parish): string
    {
        $borrowed = self::place(trim(preg_replace('/\([^)]*\)/', ' ', (string) $parish) ?? ''));

        $canton = self::place($canton);

        if (
            $borrowed !== ''
            && self::isAccented($borrowed)
            && TerritoryMatcher::normalize($borrowed) === TerritoryMatcher::normalize($canton)
        ) {
            return $borrowed;
        }

        return $canton;
    }

    /**
     * Whether a name carries any accent, which is what makes one spelling of it
     * worth more than another.
     */
    public static function isAccented(string $name): bool
    {
        return Str::ascii($name) !== $name;
    }

    /**
     * Tidy a place name: every word capitalized except the small words that
     * join them, and never the first.
     */
    public static function place(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];

        foreach ($words as $index => $word) {
            $words[$index] = self::links($word, $index, $words[$index - 1] ?? '')
                ? mb_strtolower($word)
                : self::capitalize($word);
        }

        return implode(' ', $words);
    }

    /**
     * Whether a word merely joins the two halves of a name rather than naming
     * anything itself.
     */
    protected static function links(string $word, int $index, string $previous): bool
    {
        if ($index === 0) {
            return false;
        }

        $word = mb_strtolower($word);

        return in_array($word, self::LINKERS, true)
            || (in_array($word, self::ARTICLES, true) && in_array(mb_strtolower($previous), self::LINKERS, true));
    }

    /**
     * Capitalize one word, leaving alone anything the catalog deliberately
     * wrote in mixed case.
     */
    protected static function capitalize(string $word): string
    {
        if ($word === '' || (mb_strtoupper($word) !== $word && mb_strtolower($word) !== $word)) {
            return $word;
        }

        return mb_convert_case(mb_strtolower($word), MB_CASE_TITLE, 'UTF-8');
    }
}
