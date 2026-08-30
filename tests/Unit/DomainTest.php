<?php

use Tapstar\Sendifico\Shipment\CodCalculator;
use Tapstar\Sendifico\Shipment\PayloadBuilder;
use Tapstar\Sendifico\Shipment\ShipmentStatus;
use Tapstar\Sendifico\Support\PhoneNormalizer;
use Tapstar\Sendifico\Territory\TerritoryMatcher;

/**
 * Build the in-memory territory rows the matcher walks.
 */
function territories(): array
{
    $rows = [
        ['EC|:|PICHINCHA|:|QUITO|:|QUITO', 'PICHINCHA', 'QUITO', 'QUITO', 'Pichincha - Quito - Quito'],
        ['EC|:|PICHINCHA|:|QUITO|:|TUMBACO', 'PICHINCHA', 'QUITO', 'TUMBACO', 'Pichincha - Quito - Tumbaco'],
        ['EC|:|PICHINCHA|:|QUITO|:|CUMBAYA', 'PICHINCHA', 'QUITO', 'CUMBAYA', 'Pichincha - Quito - Cumbayá'],
        ['EC|:|GUAYAS|:|GUAYAQUIL|:|GUAYAQUIL', 'GUAYAS', 'GUAYAQUIL', 'GUAYAQUIL', 'Guayas - Guayaquil - Guayaquil'],
        ['EC|:|AZUAY|:|CUENCA|:|CUENCA', 'AZUAY', 'CUENCA', 'CUENCA', 'Azuay - Cuenca - Cuenca'],
        ['EC|:|BOLIVAR|:|SAN MIGUEL|:|SAN MIGUEL', 'BOLIVAR', 'SAN MIGUEL', 'SAN MIGUEL', 'Bolívar - San Miguel - San Miguel'],
        ['EC|:|MANABI|:|SAN MIGUEL|:|SAN MIGUEL', 'MANABI', 'SAN MIGUEL', 'SAN MIGUEL', 'Manabí - San Miguel - San Miguel'],
    ];

    return array_map(fn ($row) => (object) [
        'territory_base_id' => $row[0],
        'territory1_name' => $row[1],
        'territory2_name' => $row[2],
        'territory3_name' => $row[3],
        'searchable_text' => $row[4],
    ], $rows);
}

it('matches a canton exactly, with and without a province', function () {
    // Act & Assert
    expect(TerritoryMatcher::match(territories(), 'Pichincha', 'Quito'))
        ->toBe('EC|:|PICHINCHA|:|QUITO|:|QUITO');

    expect(TerritoryMatcher::match(territories(), '', 'Guayaquil'))
        ->toBe('EC|:|GUAYAS|:|GUAYAQUIL|:|GUAYAQUIL');
});

it('resolves a multi parish canton to its urban parish of the same name', function () {
    // Act & Assert
    expect(TerritoryMatcher::match(territories(), '', 'Quito'))
        ->toBe('EC|:|PICHINCHA|:|QUITO|:|QUITO');
});

it('matches a parish name used as the city', function () {
    // Act & Assert
    expect(TerritoryMatcher::match(territories(), '', 'Tumbaco'))
        ->toBe('EC|:|PICHINCHA|:|QUITO|:|TUMBACO');

    expect(TerritoryMatcher::match(territories(), '', 'Cumbayá'))
        ->toBe('EC|:|PICHINCHA|:|QUITO|:|CUMBAYA');
});

it('never guesses between colliding canton names without a province', function () {
    // Act & Assert
    expect(TerritoryMatcher::match(territories(), '', 'San Miguel'))->toBeNull();

    expect(TerritoryMatcher::match(territories(), 'Manabí', 'San Miguel'))
        ->toBe('EC|:|MANABI|:|SAN MIGUEL|:|SAN MIGUEL');
});

it('tolerates accents and small typos only above the confidence bar', function () {
    // Act & Assert
    expect(TerritoryMatcher::match(territories(), 'PICHINCHA', 'quíto'))
        ->toBe('EC|:|PICHINCHA|:|QUITO|:|QUITO');

    expect(TerritoryMatcher::match(territories(), 'Guayas', 'Guayaquíl'))
        ->toBe('EC|:|GUAYAS|:|GUAYAQUIL|:|GUAYAQUIL');

    expect(TerritoryMatcher::match(territories(), '', 'Springfield'))->toBeNull();

    expect(TerritoryMatcher::match(territories(), '', ''))->toBeNull();
});

it('normalizes phones to ecuadorian e164', function () {
    // Act & Assert
    expect(PhoneNormalizer::toE164('0991234567'))->toBe('+593991234567');

    expect(PhoneNormalizer::toE164('+593 99-123-4567'))->toBe('+593991234567');

    expect(PhoneNormalizer::toE164('593991234567'))->toBe('+593991234567');

    expect(PhoneNormalizer::toE164('  '))->toBeNull();

    expect(PhoneNormalizer::toE164('abc'))->toBeNull();
});

it('collects nothing unless cod is enabled and the order is cash on delivery', function () {
    // Act & Assert
    expect(CodCalculator::calculate(false, true, CodCalculator::MODE_TOTAL, 100.0, 6.0))->toBe(0.0);

    expect(CodCalculator::calculate(true, false, CodCalculator::MODE_TOTAL, 100.0, 6.0))->toBe(0.0);

    expect(CodCalculator::calculate(true, true, CodCalculator::MODE_TOTAL, 100.0, 6.0))->toBe(100.0);

    expect(CodCalculator::calculate(true, true, CodCalculator::MODE_PRODUCTS_ONLY, 100.0, 6.0))->toBe(94.0);

    expect(CodCalculator::calculate(true, true, CodCalculator::MODE_TOTAL, 100.0, 6.0, 30.0))->toBe(70.0);
});

it('knows which statuses are terminal and how they group', function () {
    // Act & Assert
    expect(ShipmentStatus::isTerminal('delivered'))->toBeTrue();

    expect(ShipmentStatus::isTerminal('refundPending'))->toBeTrue();

    expect(ShipmentStatus::isTerminal('inTransit'))->toBeFalse();

    expect(ShipmentStatus::group('labelCreated'))->toBe('pending-pickup');

    expect(ShipmentStatus::group('goodsCollectionInTransit'))->toBe('in-transit');

    expect(ShipmentStatus::group('refunded'))->toBeNull();
});

it('builds a deferred shipment payload from an order', function () {
    // Arrange
    $order = (object) [
        'increment_id' => '42',
        'customer_email' => 'fallback@example.com',
        'shipping_address' => (object) [
            'first_name' => 'María',
            'last_name' => 'García',
            'company_name' => null,
            'address' => "Av. Amazonas N24-03\nEdificio Plaza",
            'city' => 'Quito',
            'state' => 'Pichincha',
            'postcode' => '170135',
            'phone' => '0991234567',
            'email' => null,
        ],
        'billing_address' => null,
    ];

    // Act
    $payload = PayloadBuilder::build($order, 2001234, 'EC|:|PICHINCHA|:|QUITO|:|QUITO', 'cellularPhone', 12.5);

    // Assert
    expect($payload['extId'])->toBe('bagisto-42');

    expect($payload['senderAddressId'])->toBe(2001234);

    expect($payload['contents'])->toBe(['cellularPhone']);

    expect($payload['goodsCollection'])->toBe(12.5);

    expect($payload['goodsCurrency'])->toBe('USD');

    expect($payload['integration']['platform'])->toBe('bagisto');

    expect($payload)->not->toHaveKey('parcel');

    expect($payload['recipientAddress']['fullName'])->toBe('María García');

    expect($payload['recipientAddress']['streetLine1'])->toBe('Av. Amazonas N24-03, Edificio Plaza');

    expect($payload['recipientAddress']['phone'])->toBe('+593991234567');

    expect($payload['recipientAddress']['email'])->toBe('fallback@example.com');

    expect($payload['recipientAddress'])->not->toHaveKey('company');
});
