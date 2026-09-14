<?php

namespace Tapstar\Sendifico\Carriers;

use Illuminate\Support\Collection;
use Tapstar\Sendifico\Territory\TerritoryMatcher;
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
     * Calculate the rate: charged once per order or once per shippable unit, waived above the free-shipping threshold,
     * plus the surcharge of the destination province, which applies even when shipping is otherwise free.
     *
     * @return CartShippingRate|false
     */
    public function calculate()
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $cart = Cart::getCart();

        $baseRate = ($this->isFreeFor($cart) ? 0.0 : $this->rateFor($cart)) + $this->surchargeFor($cart);

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
     * Price the cart: the configured rate once, or once for every unit of a shippable product. Products marked
     * for free shipping add nothing, so a cart holding only those ships free.
     *
     * @param  \Webkul\Checkout\Contracts\Cart  $cart
     * @return float
     */
    protected function rateFor($cart)
    {
        $items = $this->chargeableItems($cart);

        if ($items->isEmpty()) {
            return 0.0;
        }

        $rate = (float) $this->getConfigData('default_rate');

        if ($this->getConfigData('rate_mode') !== 'per_item') {
            return $rate;
        }

        return $rate * (int) $items->sum('quantity');
    }

    /**
     * The cart's shippable items whose product is not marked for free shipping. A configurable product's
     * variants follow the toggle on the product itself.
     *
     * @param  \Webkul\Checkout\Contracts\Cart  $cart
     * @return Collection
     */
    protected function chargeableItems($cart)
    {
        return $cart->items
            ->filter(fn ($item) => $item->getTypeInstance()->isStockable())
            ->reject(fn ($item) => (bool) $item->product?->free_shipping);
    }

    /**
     * The surcharge configured for the province of the shipping address, one "PROVINCE: amount" per line.
     *
     * @param  \Webkul\Checkout\Contracts\Cart  $cart
     * @return float
     */
    protected function surchargeFor($cart)
    {
        $province = TerritoryMatcher::normalize($cart->shipping_address?->state);

        if (! $province) {
            return 0.0;
        }

        foreach (preg_split('/\R/', (string) $this->getConfigData('province_surcharges')) as $line) {
            [$name, $amount] = array_pad(explode(':', $line, 2), 2, '');

            if (
                is_numeric(trim($amount))
                && TerritoryMatcher::normalize($name) === $province
            ) {
                return (float) trim($amount);
            }
        }

        return 0.0;
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
