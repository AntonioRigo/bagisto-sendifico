<?php

namespace Tapstar\Sendifico\Shipment;

class CodCalculator
{
    /**
     * Collect the order's full grand total on delivery.
     *
     * @var string
     */
    public const MODE_TOTAL = 'total';

    /**
     * Collect only the products' value, without shipping.
     *
     * @var string
     */
    public const MODE_PRODUCTS_ONLY = 'products_only';

    /**
     * Calculate the amount the carrier must collect on delivery.
     *
     * Zero unless collection is both enabled and the order was placed as cash on
     * delivery. Any amount the customer already paid in advance is deducted, so a
     * future prepaid-shipping setup only has to pass what was collected upfront.
     *
     * @param  bool  $enabled
     * @param  bool  $isCashOnDelivery
     * @param  string  $mode
     * @param  float  $grandTotal
     * @param  float  $shippingAmount
     * @param  float  $prepaidAmount
     * @return float
     */
    public static function calculate($enabled, $isCashOnDelivery, $mode, $grandTotal, $shippingAmount, $prepaidAmount = 0.0)
    {
        if (
            ! $enabled
            || ! $isCashOnDelivery
        ) {
            return 0.0;
        }

        $amount = $mode === self::MODE_PRODUCTS_ONLY
            ? $grandTotal - $shippingAmount
            : $grandTotal;

        return round(max(0.0, $amount - $prepaidAmount), 2);
    }
}
