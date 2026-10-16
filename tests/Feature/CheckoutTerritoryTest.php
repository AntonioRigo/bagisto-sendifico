<?php

use Illuminate\Support\Facades\Cache;
use Tapstar\Sendifico\Http\Requests\EcuadorCartAddressRequest;
use Tapstar\Sendifico\Repositories\TerritoryRepository;

beforeEach(function () {
    Cache::flush();

    $this->seedTerritories();
});

it('serves the province tree to the checkout address form', function () {
    // Act
    $response = $this->get(route('shop.sendifico.territories'));

    // Assert
    $response->assertOk();

    $tree = $response->json('data');

    expect($tree)->toHaveKeys(['Guayas', 'Pichincha']);

    expect($tree['Pichincha'])->toBe(['Quito']);

    expect($tree['Guayas'])->toBe(['Guayaquil']);
});

it('collapses the parishes of a canton into a single entry', function () {
    // Act
    $tree = app(TerritoryRepository::class)->provinceTree('EC');

    // Assert
    expect($tree['Pichincha'])->toBe(['Quito']);
});

it('offers each canton under the name a customer would recognise', function () {
    // Arrange
    app(TerritoryRepository::class)->replaceForCountry('EC', [
        ['territoryBaseId' => 'EC|:|TUNGURAHUA|:|BANOS_DE_AGUA_SANTA|:|BAÑOS_DE_AGUA_SANTA_(TU)', 'territory1Name' => 'TUNGURAHUA', 'territory2Name' => 'BANOS DE AGUA SANTA', 'territory3Name' => 'BAÑOS DE AGUA SANTA (TU)', 'searchableText' => 'Tungurahua - Banos De Agua Santa - Baños De Agua Santa (TU)'],
        ['territoryBaseId' => 'EC|:|SANTO_DOMINGO_DE_LOS_TSACHILAS|:|SANTO_DOMINGO|:|ZARACAY', 'territory1Name' => 'SANTO DOMINGO DE LOS TSACHILAS', 'territory2Name' => 'SANTO DOMINGO', 'territory3Name' => 'ZARACAY', 'searchableText' => 'Santo Domingo De Los Tsachilas - Santo Domingo - Zaracay'],
    ]);

    // Act
    $tree = app(TerritoryRepository::class)->provinceTree('EC');

    // Assert
    expect($tree['Tungurahua'])->toBe(['Baños de Agua Santa']);

    expect($tree['Santo Domingo de los Tsáchilas'])->toBe(['Santo Domingo']);
});

it('lists provinces and their cantons in alphabetical order', function () {
    // Arrange
    app(TerritoryRepository::class)->replaceForCountry('EC', [
        ['territoryBaseId' => 'EC|:|PICHINCHA|:|RUMINAHUI|:|SANGOLQUI', 'territory1Name' => 'PICHINCHA', 'territory2Name' => 'RUMINAHUI', 'territory3Name' => 'SANGOLQUI', 'searchableText' => 'Pichincha - Rumiñahui - Sangolquí'],
        ['territoryBaseId' => 'EC|:|PICHINCHA|:|QUITO|:|QUITO', 'territory1Name' => 'PICHINCHA', 'territory2Name' => 'QUITO', 'territory3Name' => 'QUITO', 'searchableText' => 'Pichincha - Quito - Quito'],
        ['territoryBaseId' => 'EC|:|AZUAY|:|CUENCA|:|CUENCA', 'territory1Name' => 'AZUAY', 'territory2Name' => 'CUENCA', 'territory3Name' => 'CUENCA', 'searchableText' => 'Azuay - Cuenca - Cuenca'],
    ]);

    // Act
    $tree = app(TerritoryRepository::class)->provinceTree('EC');

    // Assert
    expect(array_keys($tree))->toBe(['Azuay', 'Pichincha']);

    expect($tree['Pichincha'])->toBe(['Quito', 'Ruminahui']);
});

it('accepts an address without a postal code, and pins the country to Ecuador', function () {
    // Arrange
    $request = new EcuadorCartAddressRequest([
        'billing' => ['country' => 'EC', 'use_for_shipping' => true],
    ]);

    // Act
    $rules = $request->rules();

    // Assert
    expect($rules['billing.postcode'][0])->toBe('nullable');

    expect($rules['billing.country'])->toBe(['required', 'in:EC']);

    expect($rules['billing.vat_id'][0])->toBe('required');
});
