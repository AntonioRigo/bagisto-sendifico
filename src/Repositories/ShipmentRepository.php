<?php

namespace Tapstar\Sendifico\Repositories;

use Webkul\Core\Eloquent\Repository;

class ShipmentRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Tapstar\Sendifico\Contracts\Shipment';
    }

    /**
     * Get the shipments the status sync still needs to poll.
     */
    public function findSyncable(int $limit = 100)
    {
        return $this->getModel()
            ->newQuery()
            ->whereNotNull('shipment_id')
            ->where('is_terminal', false)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }
}
