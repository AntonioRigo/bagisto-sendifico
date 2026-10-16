<?php

namespace Tapstar\Sendifico\Territory;

use Illuminate\Support\Str;

class TerritoryMatcher
{
    /**
     * Similarity threshold (0-100) a fuzzy match must clear when no exact match exists.
     *
     * @var float
     */
    public const FUZZY_THRESHOLD = 92.0;

    /**
     * The cantones whose seat the carrier's catalog does not name after the
     * cantón itself, so no amount of comparing the two names can find it.
     * Each entry names the parroquia that is the cantón's seat, and it is
     * looked up across the province because a few of them are filed under a
     * differently spelled cantón.
     *
     * @var array<string, string>
     */
    public const SEATS = [
        'carchi|espejo' => 'el angel',
        'esmeraldas|eloy alfaro' => 'valdez',
        'galapagos|san cristobal' => 'puerto baquerizo moreno',
        'guayas|coronel marcelino mariduena' => 'marcelino mariduenas',
        'imbabura|antonio ante' => 'atuntaqui',
        'loja|calvas' => 'cariamanga',
        'loja|espindola' => 'amaluza',
        'morona santiago|morona' => 'macas',
        'napo|quijos' => 'baeza',
        'napo|tena' => 'el tena',
        'pastaza|pastaza' => 'el puyo',
        'pichincha|pedro moncayo' => 'tabacundo',
        'pichincha|ruminahui' => 'sangolqui',
        'zamora chinchipe|centinela del condor' => 'zumbi',
        'zamora chinchipe|chinchipe' => 'zumba',
    ];

    /**
     * Resolve a territory from the free-text province and city of an order address.
     *
     * The province is optional because many stores never configure states for Ecuador. A
     * "city" may really be the name of a parroquia (Tumbaco, Cumbayá, Conocoto), so a
     * parish-level match is tried before falling back to a high-confidence fuzzy match.
     * When several parroquias share the cantón, the carrier prices from the cantón's
     * seat, which this finds by name. Anything less certain resolves to null — a match
     * is never invented.
     *
     * @param  iterable  $territories
     * @param  string  $province
     * @param  string  $city
     * @return string|null
     */
    public static function match($territories, $province, $city)
    {
        $provinceNorm = self::normalize($province);

        $cityNorm = self::normalize($city);

        if ($cityNorm === '') {
            return null;
        }

        $hasProvince = $provinceNorm !== '';

        $inProvince = [];

        $cantonMatches = [];

        $parishMatches = [];

        foreach ($territories as $territory) {
            if (
                $hasProvince
                && self::normalize($territory->territory1_name) !== $provinceNorm
            ) {
                continue;
            }

            $inProvince[] = $territory;

            if (self::normalize($territory->territory2_name) === $cityNorm) {
                $cantonMatches[] = $territory;
            } elseif (self::normalize((string) $territory->territory3_name) === $cityNorm) {
                $parishMatches[] = $territory;
            }
        }

        if (count($cantonMatches) === 1) {
            return $cantonMatches[0]->territory_base_id;
        }

        if ($cantonMatches !== []) {
            return self::namedSeat($inProvince, $provinceNorm, (string) $city)
                ?? self::seat($cantonMatches, (string) $city);
        }

        if (count($parishMatches) === 1) {
            return $parishMatches[0]->territory_base_id;
        }

        if ($parishMatches !== []) {
            return null;
        }

        return self::fuzzyMatch($territories, $provinceNorm, $cityNorm);
    }

    /**
     * Normalize free text for comparison: lowercase, unaccented, single-spaced.
     *
     * @param  string|null  $value
     * @return string
     */
    public static function normalize($value)
    {
        $value = Str::ascii(mb_strtolower(trim((string) $value)));

        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * A name with its parenthesised asides removed. The catalog tells two
     * places of the same name apart by appending the province — "Baños de Agua
     * Santa (TU)" — and that aside is never part of what an address calls the
     * place.
     *
     * @param  string|null  $value
     */
    public static function base($value): string
    {
        return self::normalize(preg_replace('/\([^)]*\)/', ' ', (string) $value) ?? '');
    }

    /**
     * Every name a place may be known by: the whole name, the name without its
     * asides, and each aside on its own, since the catalog sometimes files the
     * familiar name there — "Nueva Loja (Lago Agrio)".
     *
     * @param  string|null  $value
     * @return array<int, string>
     */
    protected static function aliases($value): array
    {
        $aliases = [self::normalize($value), self::base($value)];

        preg_match_all('/\(([^)]*)\)/', (string) $value, $asides);

        foreach ($asides[1] as $aside) {
            $aliases[] = self::normalize($aside);
        }

        return array_values(array_unique(array_filter($aliases)));
    }

    /**
     * A normalized name with its spaces closed up, so that "Sigsig" and "Sig
     * Sig" are read as the one place they are.
     */
    protected static function compact(string $value): string
    {
        return str_replace(' ', '', $value);
    }

    /**
     * The parroquia a cantón is administered from, chosen by comparing its name
     * with the cantón's in ever looser terms and stopping at the first
     * comparison that singles one out. An inconclusive comparison resolves to
     * null rather than guessing between parroquias.
     *
     * @param  array<int, object>  $cantonMatches
     */
    protected static function seat(array $cantonMatches, string $city): ?string
    {
        $cityBase = self::base($city);

        $cityAliases = self::aliases($city);

        $comparisons = [
            fn ($territory) => self::base($territory->territory3_name) === $cityBase,
            fn ($territory) => self::compact(self::base($territory->territory3_name)) === self::compact($cityBase),
            fn ($territory) => array_intersect(self::aliases($territory->territory3_name), $cityAliases) !== [],
            fn ($territory) => self::sharesName(self::base($territory->territory3_name), $cityBase),
        ];

        foreach ($comparisons as $comparison) {
            $seats = array_values(array_filter($cantonMatches, $comparison));

            if (count($seats) === 1) {
                return $seats[0]->territory_base_id;
            }
        }

        return null;
    }

    /**
     * The seat named for this cantón in the table above, looked up among the
     * province's parroquias.
     *
     * @param  array<int, object>  $inProvince
     */
    protected static function namedSeat(array $inProvince, string $provinceNorm, string $city): ?string
    {
        $seat = self::SEATS[$provinceNorm.'|'.self::base($city)] ?? null;

        if (! $seat || $provinceNorm === '') {
            return null;
        }

        $matches = array_values(array_filter(
            $inProvince,
            fn ($territory) => self::base($territory->territory3_name) === $seat
        ));

        return count($matches) === 1 ? $matches[0]->territory_base_id : null;
    }

    /**
     * Whether one place name contains the other in full, as whole words:
     * "Santiago de Píllaro" and its seat "Píllaro" are the same place.
     */
    protected static function sharesName(string $parish, string $canton): bool
    {
        if ($parish === '' || $canton === '') {
            return false;
        }

        return self::holds($parish, $canton) || self::holds($canton, $parish);
    }

    /**
     * Whether a name carries another inside it on word boundaries.
     */
    protected static function holds(string $haystack, string $needle): bool
    {
        return (bool) preg_match('/(^|\s)'.preg_quote($needle, '/').'(\s|$)/', $haystack);
    }

    /**
     * Fall back to a similarity comparison, accepted only above a high confidence bar.
     *
     * @param  iterable  $territories
     * @return string|null
     */
    protected static function fuzzyMatch($territories, string $provinceNorm, string $cityNorm)
    {
        $needle = trim($provinceNorm.' '.$cityNorm);

        $bestScore = 0.0;

        $bestTerritoryBaseId = null;

        foreach ($territories as $territory) {
            $haystacks = array_unique(array_filter([
                self::normalize($territory->territory1_name.' '.$territory->territory2_name),
                self::normalize($territory->territory1_name.' '.$territory->territory3_name),
                self::normalize($territory->searchable_text),
            ]));

            foreach ($haystacks as $haystack) {
                similar_text($needle, $haystack, $percent);

                if ($percent > $bestScore) {
                    $bestScore = $percent;

                    $bestTerritoryBaseId = $territory->territory_base_id;
                }
            }
        }

        return $bestScore >= self::FUZZY_THRESHOLD ? $bestTerritoryBaseId : null;
    }
}
