<?php

namespace Tapstar\Sendifico\Services;

use Tapstar\Sendifico\Api\Client;
use Tapstar\Sendifico\Repositories\TerritoryMatchRepository;
use Tapstar\Sendifico\Repositories\TerritoryRepository;
use Tapstar\Sendifico\Territory\TerritoryMatcher;

class TerritoryService
{
    /**
     * Create a new service instance.
     *
     * @return void
     */
    public function __construct(
        protected Client $client,
        protected TerritoryRepository $territoryRepository,
        protected TerritoryMatchRepository $territoryMatchRepository,
    ) {}

    /**
     * Download the territory catalog and replace the local cache.
     *
     * @return int
     */
    public function refresh()
    {
        $response = $this->client->territories();

        $response->throw();

        return $this->territoryRepository->replaceForCountry(
            Client::COUNTRY,
            $response->json('payload.data') ?: []
        );
    }

    /**
     * Refresh the local catalog only when it has gone stale.
     */
    public function refreshIfStale(int $maxAgeDays = 7): void
    {
        if (! $this->territoryRepository->isStale(Client::COUNTRY, $maxAgeDays)) {
            return;
        }

        try {
            $this->refresh();
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Resolve a territory for a free-text province and city, learning confident matches.
     *
     * @return string|null
     */
    public function resolve(?string $province, ?string $city)
    {
        $provinceNorm = TerritoryMatcher::normalize($province);

        $cityNorm = TerritoryMatcher::normalize($city);

        if ($cityNorm === '') {
            return null;
        }

        if ($learned = $this->territoryMatchRepository->findLearned(Client::COUNTRY, $provinceNorm, $cityNorm)) {
            return $learned;
        }

        $match = TerritoryMatcher::match(
            $this->territoryRepository->allForCountry(Client::COUNTRY),
            (string) $province,
            (string) $city
        );

        if ($match !== null) {
            $this->territoryMatchRepository->learn(
                Client::COUNTRY,
                $provinceNorm,
                $cityNorm,
                $match,
                TerritoryMatchRepository::SOURCE_AUTO
            );
        }

        return $match;
    }

    /**
     * Remember the operator's manual territory choice for this city.
     */
    public function confirm(?string $province, ?string $city, string $territoryBaseId): void
    {
        $this->territoryMatchRepository->learn(
            Client::COUNTRY,
            TerritoryMatcher::normalize($province),
            TerritoryMatcher::normalize($city),
            $territoryBaseId,
            TerritoryMatchRepository::SOURCE_MANUAL
        );
    }

    /**
     * Get the cached catalog for pickers.
     */
    public function all()
    {
        return $this->territoryRepository->allForCountry(Client::COUNTRY);
    }
}
