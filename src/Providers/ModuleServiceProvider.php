<?php

namespace Tapstar\Sendifico\Providers;

use Tapstar\Sendifico\Models\Log;
use Tapstar\Sendifico\Models\Shipment;
use Tapstar\Sendifico\Models\Territory;
use Tapstar\Sendifico\Models\TerritoryMatch;
use Webkul\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        Shipment::class,
        Territory::class,
        TerritoryMatch::class,
        Log::class,
    ];
}
