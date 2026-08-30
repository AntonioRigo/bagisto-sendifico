<?php

use Tapstar\Sendifico\Carriers\Sendifico;
use Webkul\Checkout\Facades\Cart;

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

it('is unavailable when disabled', function () {
    // Arrange
    $this->setSendificoConfig('active', '0');

    $cart = $this->createCartWithItems('payphone', ['base_currency_code' => 'USD']);

    Cart::setCart($cart);

    // Act & Assert
    expect(app(Sendifico::class)->calculate())->toBeFalse();
});
