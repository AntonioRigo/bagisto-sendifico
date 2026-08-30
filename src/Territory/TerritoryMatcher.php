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
     * Resolve a territory from the free-text province and city of an order address.
     *
     * The province is optional because many stores never configure states for Ecuador. A
     * "city" may really be the name of a parroquia (Tumbaco, Cumbayá, Conocoto), so a
     * parish-level match is tried before falling back to a high-confidence fuzzy match.
     * When several parroquias share the cantón, the one named like the cantón itself is
     * the conventional urban centre and wins. Anything less certain resolves to null —
     * a match is never invented.
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

        $cantonMatches = [];

        $parishMatches = [];

        foreach ($territories as $territory) {
            if (
                $hasProvince
                && self::normalize($territory->territory1_name) !== $provinceNorm
            ) {
                continue;
            }

            if (self::normalize($territory->territory2_name) === $cityNorm) {
                $cantonMatches[] = $territory;
            } elseif (self::normalize((string) $territory->territory3_name) === $cityNorm) {
                $parishMatches[] = $territory;
            }
        }

        if (count($cantonMatches) === 1) {
            return $cantonMatches[0]->territory_base_id;
        }

        if (count($cantonMatches) > 1) {
            $capitals = array_values(array_filter(
                $cantonMatches,
                fn ($territory) => self::normalize((string) $territory->territory3_name) === $cityNorm
            ));

            return count($capitals) === 1 ? $capitals[0]->territory_base_id : null;
        }

        if (count($parishMatches) === 1) {
            return $parishMatches[0]->territory_base_id;
        }

        if (count($parishMatches) > 1) {
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
