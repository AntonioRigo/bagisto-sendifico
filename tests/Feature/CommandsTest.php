<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tapstar\Sendifico\Console\Commands\SyncShipments;
use Tapstar\Sendifico\Models\Shipment;
use Tapstar\Sendifico\Models\Territory;

beforeEach(function () {
    $this->configureSendifico();
});

it('downloads the territory catalog into the local cache', function () {
    // Arrange
    Http::fake([
        'api.sendifico.com/api/public/territory' => Http::response([
            'payload' => [
                'data' => [
                    ['territoryBaseId' => 'EC|:|PICHINCHA|:|QUITO|:|QUITO', 'territory1Name' => 'PICHINCHA', 'territory2Name' => 'QUITO', 'territory3Name' => 'QUITO', 'searchableText' => 'Pichincha - Quito - Quito'],
                    ['territoryBaseId' => 'EC|:|AZUAY|:|CUENCA|:|CUENCA', 'territory1Name' => 'AZUAY', 'territory2Name' => 'CUENCA', 'territory3Name' => 'CUENCA', 'searchableText' => 'Azuay - Cuenca - Cuenca'],
                ],
            ],
            'objectType' => 'territory',
        ]),
    ]);

    // Act & Assert
    $this->artisan('sendifico:territories')->assertExitCode(0);

    expect(Territory::where('country', 'EC')->count())->toBe(2);
});

it('syncs non terminal shipments and leaves terminal ones alone', function () {
    // Arrange
    $this->loginAsAdmin();

    $active = $this->createOrderFor('Quito', 'Pichincha');

    $done = $this->createOrderFor('Guayaquil', 'Guayas');

    Shipment::create([
        'order_id' => $active->id,
        'shipment_id' => 4001111,
        'status' => 'labelCreated',
        'is_paid' => true,
    ]);

    Shipment::create([
        'order_id' => $done->id,
        'shipment_id' => 4002222,
        'status' => 'delivered',
        'is_paid' => true,
        'is_terminal' => true,
    ]);

    Http::fake([
        'api.sendifico.com/api/public/shipment/4001111' => Http::response([
            'payload' => [
                'shipmentId' => 4001111,
                'status' => 'delivered',
                'isPaid' => true,
                'trackingNumber' => 'LAAR-EC-1',
                'trackingCarrierUrl' => 'https://tracking.sendifico.com/LAAR-EC-1',
                'incidentCount' => 0,
            ],
            'objectType' => 'shipment',
        ]),
    ]);

    Cache::forget(SyncShipments::LAST_RUN_KEY);

    // Act
    $this->artisan('sendifico:sync')->assertExitCode(0);

    // Assert
    expect(Shipment::where('order_id', $active->id)->first()->is_terminal)->toBeTrue();

    Http::assertSentCount(1);
});

it('honours the mandatory four hour interval between syncs', function () {
    // Arrange
    Http::fake();

    Cache::forever(SyncShipments::LAST_RUN_KEY, time());

    // Act
    $this->artisan('sendifico:sync')->assertExitCode(0);

    // Assert
    Http::assertNothingSent();
});
