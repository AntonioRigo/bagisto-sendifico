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

    expect($tree)->toHaveKeys(['GUAYAS', 'PICHINCHA']);

    expect($tree['PICHINCHA'])->toBe(['QUITO']);

    expect($tree['GUAYAS'])->toBe(['GUAYAQUIL']);
});

it('collapses the parishes of a canton into a single entry', function () {
    // Act
    $tree = app(TerritoryRepository::class)->provinceTree('EC');

    // Assert
    expect($tree['PICHINCHA'])->toBe(['QUITO']);
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
    expect(array_keys($tree))->toBe(['AZUAY', 'PICHINCHA']);

    expect($tree['PICHINCHA'])->toBe(['QUITO', 'RUMINAHUI']);
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
