<?php

use Tapstar\Sendifico\Carriers\Sendifico;
use Webkul\Checkout\Facades\Cart;
use Webkul\Faker\Helpers\Product as ProductFaker;

it('charges the flat rate below the free shipping threshold', function () {
    // Arrange
    $this->configureSendifico();

    $cart = $this->createCartWithItems('payphone', ['base_currency_code' => 'USD']);

    Cart::setCart($cart);

    $cart->base_sub_total = 10;

    // Act
    $rate = app(Sendifico::class)->calculate();

    // Assert
    expect($rate)->not->toBeFalse();

    expect($rate->carrier)->toBe('sendifico');

    expect($rate->method)->toBe('sendifico_standard');

    expect((float) $rate->base_price)->toBe(6.0);
});

it('ships free once the subtotal reaches the threshold', function () {
    // Arrange
    $this->configureSendifico();

    $cart = $this->createCartWithItems('payphone', ['base_currency_code' => 'USD']);

    Cart::setCart($cart);

    $cart->base_sub_total = 25;

    // Act
    $rate = app(Sendifico::class)->calculate();

    // Assert
    expect((float) $rate->base_price)->toBe(0.0);
});

it('charges the rate once per shippable unit in per-item mode', function () {
    // Arrange
    $this->configureSendifico([
        'default_rate' => '8',
        'rate_mode' => 'per_item',
        'free_threshold' => '0',
    ]);

    $cart = $this->createCartWithItems('payphone', ['base_currency_code' => 'USD']);

    $cart->items->first()->update(['quantity' => 3]);

    $cart->refresh();

    Cart::setCart($cart);

    $units = $cart->items->sum('quantity');

    // Act
    $rate = app(Sendifico::class)->calculate();

    // Assert
    expect($units)->toBeGreaterThanOrEqual(3);

    expect((float) $rate->base_price)->toBe(8.0 * $units);
});

it('keeps charging per unit when the free shipping threshold is disabled', function () {
    // Arrange
    $this->configureSendifico([
        'default_rate' => '8',
        'rate_mode' => 'per_item',
        'free_threshold' => '0',
    ]);

    $cart = $this->createCartWithItems('payphone', ['base_currency_code' => 'USD']);

    Cart::setCart($cart);

    $cart->base_sub_total = 5000;

    // Act
    $rate = app(Sendifico::class)->calculate();

    // Assert
    expect((float) $rate->base_price)->toBeGreaterThan(0.0);
});

it('ships free when every shippable item is marked for free shipping', function () {
    // Arrange
    $this->configureSendifico([
        'default_rate' => '8',
        'rate_mode' => 'per_item',
        'free_threshold' => '0',
    ]);

    $cart = $this->createCartWithItems('payphone', ['base_currency_code' => 'USD']);

    $this->markFreeShipping($cart->items->first()->product);

    Cart::setCart($cart->fresh());

    // Act
    $rate = app(Sendifico::class)->calculate();

    // Assert
    expect((float) $rate->base_price)->toBe(0.0);
});

it('charges only the shippable units of products without free shipping', function () {
    // Arrange
    $this->configureSendifico([
        'default_rate' => '8',
        'rate_mode' => 'per_item',
        'free_threshold' => '0',
    ]);

    $cart = $this->createCartWithItems('payphone', ['base_currency_code' => 'USD']);

    $freeItem = $cart->items->first();

    $this->markFreeShipping($freeItem->product);

    $chargedProduct = (new ProductFaker([]))->getSimpleProductFactory()->create();

    $chargedItem = $freeItem->replicate();

    $chargedItem->product_id = $chargedProduct->id;

    $chargedItem->sku = $chargedProduct->sku;

    $chargedItem->quantity = 2;

    $chargedItem->save();

    Cart::setCart($cart->fresh());

    // Act
    $rate = app(Sendifico::class)->calculate();

    // Assert
    expect((float) $rate->base_price)->toBe(16.0);
});

it('waives the per-order rate when the cart holds only free-shipping products', function () {
    // Arrange
    $this->configureSendifico(['free_threshold' => '0']);

    $cart = $this->createCartWithItems('payphone', ['base_currency_code' => 'USD']);

    $this->markFreeShipping($cart->items->first()->product);

    Cart::setCart($cart->fresh());

    // Act
    $rate = app(Sendifico::class)->calculate();

    // Assert
    expect((float) $rate->base_price)->toBe(0.0);
});

it('adds the surcharge of the destination province to the rate', function () {
    // Arrange
    $this->configureSendifico([
        'default_rate' => '8',
        'rate_mode' => 'per_item',
        'free_threshold' => '0',
        'province_surcharges' => "PICHINCHA: 3\nGalápagos: 10",
    ]);

    $cart = $this->createCartWithItems('payphone', ['base_currency_code' => 'USD']);

    $this->shipToProvince($cart, 'GALAPAGOS');

    Cart::setCart($cart->fresh());

    // Act
    $rate = app(Sendifico::class)->calculate();

    // Assert
    expect((float) $rate->base_price)->toBe(8.0 * $cart->items->sum('quantity') + 10.0);
});

it('keeps the province surcharge when shipping is otherwise free', function () {
    // Arrange
    $this->configureSendifico([
        'default_rate' => '8',
        'rate_mode' => 'per_item',
        'free_threshold' => '0',
        'province_surcharges' => 'GALAPAGOS: 10',
    ]);

    $cart = $this->createCartWithItems('payphone', ['base_currency_code' => 'USD']);

    $this->markFreeShipping($cart->items->first()->product);

    $this->shipToProvince($cart, 'GALAPAGOS');

    Cart::setCart($cart->fresh());

    // Act
    $rate = app(Sendifico::class)->calculate();

    // Assert
    expect((float) $rate->base_price)->toBe(10.0);
});

it('adds no surcharge for a province that is not listed', function () {
    // Arrange
    $this->configureSendifico([
        'default_rate' => '8',
        'rate_mode' => 'per_item',
        'free_threshold' => '0',
        'province_surcharges' => 'GALAPAGOS: 10',
    ]);

    $cart = $this->createCartWithItems('payphone', ['base_currency_code' => 'USD']);

    $this->shipToProvince($cart, 'GUAYAS');

    Cart::setCart($cart->fresh());

    // Act
    $rate = app(Sendifico::class)->calculate();

    // Assert
    expect((float) $rate->base_price)->toBe(8.0 * $cart->items->sum('quantity'));
});

it('is unavailable when disabled', function () {
    // Arrange
    $this->setSendificoConfig('active', '0');

    $cart = $this->createCartWithItems('payphone', ['base_currency_code' => 'USD']);

    Cart::setCart($cart);

    // Act & Assert
    expect(app(Sendifico::class)->calculate())->toBeFalse();
});
