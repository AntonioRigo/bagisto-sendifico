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
        ['EC|:|TUNGURAHUA|:|BANOS_DE_AGUA_SANTA|:|BAÑOS_DE_AGUA_SANTA_(TU)', 'TUNGURAHUA', 'BANOS DE AGUA SANTA', 'BAÑOS DE AGUA SANTA (TU)', 'Tungurahua - Banos De Agua Santa - Baños De Agua Santa (TU)'],
        ['EC|:|TUNGURAHUA|:|BANOS_DE_AGUA_SANTA|:|LLIGUA', 'TUNGURAHUA', 'BANOS DE AGUA SANTA', 'LLIGUA', 'Tungurahua - Banos De Agua Santa - Lligua'],
        ['EC|:|TUNGURAHUA|:|SANTIAGO_DE_PILLARO|:|PILLARO', 'TUNGURAHUA', 'SANTIAGO DE PILLARO', 'PILLARO', 'Tungurahua - Santiago De Pillaro - Pillaro'],
        ['EC|:|TUNGURAHUA|:|SANTIAGO_DE_PILLARO|:|SAN_MIGUELITO', 'TUNGURAHUA', 'SANTIAGO DE PILLARO', 'SAN MIGUELITO', 'Tungurahua - Santiago De Pillaro - San Miguelito'],
        ['EC|:|AZUAY|:|SIG_SIG|:|SIGSIG', 'AZUAY', 'SIG SIG', 'SIGSIG', 'Azuay - Sig Sig - Sigsig'],
        ['EC|:|AZUAY|:|SIG_SIG|:|SAN_BARTOLOME', 'AZUAY', 'SIG SIG', 'SAN BARTOLOME', 'Azuay - Sig Sig - San Bartolome'],
        ['EC|:|SUCUMBIOS|:|LAGO_AGRIO|:|NUEVA_LOJA_(LAGO_AGRIO)', 'SUCUMBIOS', 'LAGO AGRIO', 'NUEVA LOJA (LAGO AGRIO)', 'Sucumbios - Lago Agrio - Nueva Loja (Lago Agrio)'],
        ['EC|:|SUCUMBIOS|:|LAGO_AGRIO|:|DURENO', 'SUCUMBIOS', 'LAGO AGRIO', 'DURENO', 'Sucumbios - Lago Agrio - Dureno'],
        ['EC|:|PICHINCHA|:|RUMINAHUI|:|SANGOLQUI', 'PICHINCHA', 'RUMINAHUI', 'SANGOLQUI', 'Pichincha - Ruminahui - Sangolqui'],
        ['EC|:|PICHINCHA|:|RUMINAHUI|:|COTOGCHOA', 'PICHINCHA', 'RUMINAHUI', 'COTOGCHOA', 'Pichincha - Ruminahui - Cotogchoa'],
        ['EC|:|NAPO|:|TENA|:|AHUANO', 'NAPO', 'TENA', 'AHUANO', 'Napo - Tena - Ahuano'],
        ['EC|:|NAPO|:|TENA|:|PANO', 'NAPO', 'TENA', 'PANO', 'Napo - Tena - Pano'],
        ['EC|:|NAPO|:|EL_TENA|:|EL_TENA', 'NAPO', 'EL TENA', 'EL TENA', 'Napo - El Tena - El Tena'],
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

it('reads past the province the catalog appends to a parish name', function () {
    // Act & Assert
    expect(TerritoryMatcher::match(territories(), 'Tungurahua', 'Baños de Agua Santa'))
        ->toBe('EC|:|TUNGURAHUA|:|BANOS_DE_AGUA_SANTA|:|BAÑOS_DE_AGUA_SANTA_(TU)');
});

it('takes the seat whose name the canton carries, however it is spaced', function () {
    // Act & Assert
    expect(TerritoryMatcher::match(territories(), 'Tungurahua', 'Santiago de Pillaro'))
        ->toBe('EC|:|TUNGURAHUA|:|SANTIAGO_DE_PILLARO|:|PILLARO');

    expect(TerritoryMatcher::match(territories(), 'Azuay', 'Sig Sig'))
        ->toBe('EC|:|AZUAY|:|SIG_SIG|:|SIGSIG');
});

it('takes the seat the catalog files under its familiar name', function () {
    // Act & Assert
    expect(TerritoryMatcher::match(territories(), 'Sucumbios', 'Lago Agrio'))
        ->toBe('EC|:|SUCUMBIOS|:|LAGO_AGRIO|:|NUEVA_LOJA_(LAGO_AGRIO)');
});

it('knows the seats that share no name with their canton', function () {
    // Act & Assert
    expect(TerritoryMatcher::match(territories(), 'Pichincha', 'Rumiñahui'))
        ->toBe('EC|:|PICHINCHA|:|RUMINAHUI|:|SANGOLQUI');

    expect(TerritoryMatcher::match(territories(), 'Napo', 'Tena'))
        ->toBe('EC|:|NAPO|:|EL_TENA|:|EL_TENA');
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

it('strips the country code so carriers get a national number', function (string $input, string $expected) {
    // Act & Assert
    expect(PhoneNormalizer::toNational($input))->toBe($expected);
})->with([
    'plain national' => ['0998110137', '0998110137'],
    'without trunk zero' => ['998110137', '0998110137'],
    'e164' => ['+593998110137', '0998110137'],
    'e164 spaced' => ['+593 99 811 0137', '0998110137'],
    'bracketed' => ['(+593) 998110137', '0998110137'],
    'bracketed keeping the zero' => ['(+593) 0998110137', '0998110137'],
    'country code without plus' => ['593998110137', '0998110137'],
    'country code and trunk zero' => ['5930998110137', '0998110137'],
    'international prefix' => ['00593998110137', '0998110137'],
    'punctuated' => ['+593-99-811-0137', '0998110137'],
    'dotted' => ['+593.99.811.0137', '0998110137'],
    'landline' => ['+59342345678', '042345678'],
    'landline national' => ['042345678', '042345678'],
]);

it('keeps a short number that only looks like it carries a country code', function () {
    // Act & Assert
    expect(PhoneNormalizer::toNational('05935678'))->toBe('05935678');

    expect(PhoneNormalizer::toNational('59345678'))->toBe('059345678');
});

it('has no number to send when the input holds no digits', function () {
    // Act & Assert
    expect(PhoneNormalizer::toNational(''))->toBeNull();

    expect(PhoneNormalizer::toNational('  '))->toBeNull();

    expect(PhoneNormalizer::toNational('sin teléfono'))->toBeNull();

    expect(PhoneNormalizer::toNational(null))->toBeNull();
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

    expect($payload['recipientAddress']['phone'])->toBe('0991234567');

    expect($payload['recipientAddress']['email'])->toBe('fallback@example.com');

    expect($payload['recipientAddress'])->not->toHaveKey('company');
});
