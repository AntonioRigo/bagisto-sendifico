<?php

namespace Tapstar\Sendifico\Http\Controllers\Shop;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Tapstar\Sendifico\Api\Client;
use Tapstar\Sendifico\Repositories\TerritoryRepository;

class TerritoryController extends Controller
{
    /**
     * Minutes the province tree stays cached for the storefront.
     *
     * @var int
     */
    public const CACHE_MINUTES = 60;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected TerritoryRepository $territoryRepository) {}

    /**
     * List the provinces and their cantons for the checkout address selectors.
     */
    public function index(): JsonResponse
    {
        $tree = cache()->remember(
            'sendifico:province-tree:'.Client::COUNTRY,
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->territoryRepository->provinceTree(Client::COUNTRY)
        );

        return response()->json(['data' => $tree]);
    }
}
