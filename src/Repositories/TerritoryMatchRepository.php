<?php

namespace Tapstar\Sendifico\Repositories;

use Webkul\Core\Eloquent\Repository;

class TerritoryMatchRepository extends Repository
{
    /**
     * A match learned from the matcher's own confident resolution.
     *
     * @var string
     */
    public const SOURCE_AUTO = 'auto';

    /**
     * A match the operator confirmed by hand.
     *
     * @var string
     */
    public const SOURCE_MANUAL = 'manual';

    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Tapstar\Sendifico\Contracts\TerritoryMatch';
    }

    /**
     * Get the learned territory for a normalized address key.
     */
    public function findLearned(string $country, string $provinceNorm, string $cityNorm): ?string
    {
        $match = $this->findOneWhere([
            'country' => $country,
            'province_norm' => $provinceNorm,
            'city_norm' => $cityNorm,
        ]);

        return $match?->territory_base_id;
    }

    /**
     * Remember a resolved territory for a normalized address key.
     *
     * A manual confirmation always wins over an automatic one.
     */
    public function learn(string $country, string $provinceNorm, string $cityNorm, string $territoryBaseId, string $source): void
    {
        $existing = $this->findOneWhere([
            'country' => $country,
            'province_norm' => $provinceNorm,
            'city_norm' => $cityNorm,
        ]);

        if (! $existing) {
            $this->create([
                'country' => $country,
                'province_norm' => $provinceNorm,
                'city_norm' => $cityNorm,
                'territory_base_id' => $territoryBaseId,
                'source' => $source,
            ]);

            return;
        }

        if (
            $source === self::SOURCE_MANUAL
            || $existing->source === self::SOURCE_AUTO
        ) {
            $this->update([
                'territory_base_id' => $territoryBaseId,
                'source' => $source,
            ], $existing->id);
        }
    }
}
