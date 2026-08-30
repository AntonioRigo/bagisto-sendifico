<?php

namespace Tapstar\Sendifico\Repositories;

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
     * Provinces and their cantons are what an address needs, so parishes collapse
     * into their canton and each canton appears once.
     */
    public function provinceTree(string $country): array
    {
        $tree = [];

        foreach ($this->allForCountry($country) as $territory) {
            $tree[$territory->territory1_name][$territory->territory2_name] = true;
        }

        ksort($tree);

        return array_map(function (array $cantons) {
            $names = array_keys($cantons);

            sort($names);

            return $names;
        }, $tree);
    }
}
