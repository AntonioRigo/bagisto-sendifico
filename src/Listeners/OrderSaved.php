<?php

namespace Tapstar\Sendifico\Listeners;

use Tapstar\Sendifico\Services\ShipmentService;
use Webkul\Sales\Contracts\Order;

class OrderSaved
{
    /**
     * Create the Sendifico draft as soon as an order is placed, when so configured.
     *
     * A Sendifico failure must never break order placement, so everything is contained.
     *
     * @param  Order  $order
     */
    public static function handle($order): void
    {
        try {
            if (core()->getConfigData('sales.carriers.sendifico.auto_draft') !== 'on_order_save') {
                return;
            }

            app(ShipmentService::class)->createDraft($order);
        } catch (\Exception $e) {
            report($e);
        }
    }
}
