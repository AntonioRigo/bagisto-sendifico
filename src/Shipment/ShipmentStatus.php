<?php

namespace Tapstar\Sendifico\Shipment;

class ShipmentStatus
{
    /**
     * Local state of a row whose address could not be matched to a territory yet.
     *
     * @var string
     */
    public const TERRITORY_PENDING = 'territoryPending';

    /**
     * Statuses the sync never needs to poll again.
     *
     * @var string[]
     */
    public const TERMINAL = [
        'delivered',
        'annulled',
        'refunded',
        'refundPending',
        'returnToSender',
    ];

    /**
     * The three operator-visible groups the raw carrier statuses collapse into.
     *
     * @var array
     */
    public const GROUPS = [
        'pending-pickup' => ['created', 'quoted', 'labelCreated', 'pickupRequested', 'pickupNotFound'],
        'in-transit' => ['pickingUp', 'pickedUp', 'inTransit', 'goodsCollectionInTransit'],
        'delivered' => ['delivered'],
    ];

    /**
     * Check whether a raw status is terminal.
     *
     * @param  string  $status
     * @return bool
     */
    public static function isTerminal($status)
    {
        return in_array($status, self::TERMINAL);
    }

    /**
     * Resolve the operator-visible group of a raw status.
     *
     * @param  string  $status
     * @return string|null
     */
    public static function group($status)
    {
        foreach (self::GROUPS as $group => $statuses) {
            if (in_array($status, $statuses)) {
                return $group;
            }
        }

        return null;
    }
}
