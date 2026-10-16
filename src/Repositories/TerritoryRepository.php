<?php

namespace Tapstar\Sendifico\Repositories;

use Tapstar\Sendifico\Territory\TerritoryMatcher;
use Tapstar\Sendifico\Territory\TerritoryNames;
use Webkul\Core\Eloquent\Repository;

class TerritoryRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Tapstar\Sendifico\Contracts\Territory';
    }

    /**
     * Replace the cached catalog for a country with a fresh download.
     */
    public function replaceForCountry(string $country, array $territories): int
    {
        $this->getModel()->newQuery()->where('country', $country)->delete();

        $rows = collect($territories)
            ->filter(fn ($territory) => ! empty($territory['territoryBaseId']))
            ->map(fn ($territory) => [
                'territory_base_id' => (string) $territory['territoryBaseId'],
                'country' => $country,
                'territory1_name' => (string) ($territory['territory1Name'] ?? ''),
                'territory2_name' => (string) ($territory['territory2Name'] ?? ''),
                'territory3_name' => $territory['territory3Name'] ?? null,
                'searchable_text' => (string) ($territory['searchableText'] ?? ''),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach ($rows->chunk(500) as $chunk) {
            $this->getModel()->newQuery()->insert($chunk->all());
        }

        return $rows->count();
    }

    /**
     * Check whether the cached catalog is older than the given age, or empty.
     */
    public function isStale(string $country, int $maxAgeDays = 7): bool
    {
        $updatedAt = $this->getModel()
            ->newQuery()
            ->where('country', $country)
            ->max('updated_at');

        return ! $updatedAt || now()->subDays($maxAgeDays)->greaterThan($updatedAt);
    }

    /**
     * Get the cached catalog for a country.
     */
    public function allForCountry(string $country)
    {
        return $this->getModel()
            ->newQuery()
            ->where('country', $country)
            ->orderBy('searchable_text')
            ->get();
    }

    /**
     * Get the country's territories as a province tree, for the checkout selectors.
     *
     * Provinces and their cantones are what an address needs, so parroquias
     * collapse into their cantón and each cantón appears once, under the name a
     * customer would recognise rather than the catalog's shouted spelling.
     */
    public function provinceTree(string $country): array
    {
        $tree = [];

        foreach ($this->allForCountry($country) as $territory) {
            $province = TerritoryNames::province($territory->territory1_name);

            $canton = TerritoryNames::canton($territory->territory2_name, $territory->territory3_name);

            $key = TerritoryMatcher::normalize($canton);

            if (! isset($tree[$province][$key]) || TerritoryNames::isAccented($canton)) {
                $tree[$province][$key] = $canton;
            }
        }

        uksort($tree, fn ($a, $b) => strcmp(TerritoryMatcher::normalize($a), TerritoryMatcher::normalize($b)));

        return array_map(function (array $cantons) {
            $names = array_values($cantons);

            usort($names, fn ($a, $b) => strcmp(TerritoryMatcher::normalize($a), TerritoryMatcher::normalize($b)));

            return $names;
        }, $tree);
    }
}
