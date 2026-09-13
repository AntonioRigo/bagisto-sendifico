<?php

namespace Tapstar\Sendifico\Carriers;

use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Models\CartShippingRate;
use Webkul\Shipping\Carriers\AbstractShipping;

class Sendifico extends AbstractShipping
{
    /**
     * Shipping method carrier code.
     *
     * @var string
     */
    protected $code = 'sendifico';

    /**
     * Shipping method code.
     *
     * @var string
     */
    protected $method = 'sendifico_standard';

    /**
     * Calculate the rate: charged once per order or once per shippable unit, waived above the free-shipping threshold.
     *
     * @return CartShippingRate|false
     */
    public function calculate()
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $cart = Cart::getCart();

        $baseRate = $this->isFreeFor($cart) ? 0.0 : $this->rateFor($cart);

        $cartShippingRate = new CartShippingRate;

        $cartShippingRate->carrier = $this->getCode();
        $cartShippingRate->carrier_title = $this->getConfigData('title');
        $cartShippingRate->method = $this->getMethod();
        $cartShippingRate->method_title = $this->getConfigData('title');
        $cartShippingRate->method_description = $this->getConfigData('description');
        $cartShippingRate->price = core()->convertPrice($baseRate);
        $cartShippingRate->base_price = $baseRate;

        return $cartShippingRate;
    }

    /**
     * Price the cart: the configured rate once, or once for every unit of a shippable product.
     *
     * @param  \Webkul\Checkout\Contracts\Cart  $cart
     * @return float
     */
    protected function rateFor($cart)
    {
        $rate = (float) $this->getConfigData('default_rate');

        if ($this->getConfigData('rate_mode') !== 'per_item') {
            return $rate;
        }

        $units = (int) $cart->items
            ->filter(fn ($item) => $item->getTypeInstance()->isStockable())
            ->sum('quantity');

        return $rate * max($units, 1);
    }

    /**
     * Check whether the cart's subtotal clears the free-shipping threshold.
     *
     * @param  \Webkul\Checkout\Contracts\Cart  $cart
     * @return bool
     */
    protected function isFreeFor($cart)
    {
        $threshold = $this->getConfigData('free_threshold');

        return is_numeric($threshold)
            && (float) $threshold > 0
            && (float) $cart->base_sub_total >= (float) $threshold;
    }
}
