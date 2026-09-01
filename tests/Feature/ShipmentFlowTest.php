<?php

use Illuminate\Support\Facades\Http;
use Tapstar\Sendifico\Models\Shipment;
use Tapstar\Sendifico\Models\TerritoryMatch;

beforeEach(function () {
    $this->loginAsAdmin();

    $this->configureSendifico();

    $this->seedTerritories();
});

/**
 * The draft payload Sendifico answers with.
 */
function draftPayload(array $overrides = []): array
{
    return ['payload' => array_merge([
        'shipmentId' => 4001234,
        'extId' => 'bagisto-9999',
        'status' => 'created',
        'isPaid' => false,
        'preferredCarrierToken' => null,
        'priceTotal' => null,
        'trackingNumber' => null,
        'trackingCarrierUrl' => null,
        'incidentCount' => null,
        'rates' => [],
    ], $overrides), 'objectType' => 'shipment'];
}

it('creates a deferred draft for an order and learns the territory', function () {
    // Arrange
    $order = $this->createOrderFor('Quito', 'Pichincha');

    Http::fake([
        'api.sendifico.com/api/public/shipment' => Http::response(draftPayload(), 201),
    ]);

    // Act
    $response = $this->post(route('admin.sendifico.draft', $order->id));

    // Assert
    $response->assertRedirect();

    $response->assertSessionHas('success');

    $row = Shipment::where('order_id', $order->id)->first();

    expect($row->shipment_id)->toBe(4001234);

    expect($row->status)->toBe('created');

    expect($row->territory_base_id)->toBe('EC|:|PICHINCHA|:|QUITO|:|QUITO');

    expect(TerritoryMatch::where('city_norm', 'quito')->exists())->toBeTrue();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_starts_with($body['extId'], 'bagisto-')
            && $body['integration']['platform'] === 'bagisto'
            && $body['recipientAddress']['phone'] === '0991234567'
            && ! array_key_exists('parcel', $body);
    });
});

it('asks for a territory confirmation when the city cannot be matched', function () {
    // Arrange
    $order = $this->createOrderFor('Ciudad Inventada');

    Http::fake();

    // Act
    $response = $this->post(route('admin.sendifico.draft', $order->id));

    // Assert
    $response->assertSessionHas('warning');

    expect(Shipment::where('order_id', $order->id)->first()->status)->toBe('territoryPending');

    Http::assertNothingSent();
});

it('creates the draft after the operator confirms the territory, and remembers it', function () {
    // Arrange
    $order = $this->createOrderFor('Ciudad Inventada');

    $this->post(route('admin.sendifico.draft', $order->id));

    Http::fake([
        'api.sendifico.com/api/public/shipment' => Http::response(draftPayload(), 201),
    ]);

    // Act
    $response = $this->post(route('admin.sendifico.territory', $order->id), [
        'territory_base_id' => 'EC|:|GUAYAS|:|GUAYAQUIL|:|GUAYAQUIL',
    ]);

    // Assert
    $response->assertSessionHas('success');

    expect(Shipment::where('order_id', $order->id)->first()->shipment_id)->toBe(4001234);

    expect(TerritoryMatch::where('city_norm', 'ciudad inventada')->value('source'))->toBe('manual');
});

it('adopts the existing shipment when the external id was already used', function () {
    // Arrange
    $order = $this->createOrderFor('Quito', 'Pichincha');

    Http::fake([
        'api.sendifico.com/api/public/shipment?*' => Http::response([
            'payload' => [
                'count' => 1,
                'data' => [draftPayload(['extId' => 'bagisto-'.$order->increment_id])['payload']],
                'page' => 1,
                'pageCount' => 1,
                'total' => 1,
            ],
            'objectType' => 'shipment',
        ]),
        'api.sendifico.com/api/public/shipment' => Http::response([
            'statusCode' => 409,
            'message' => 'pApiShipmentExtIdAlreadyUsed',
        ], 409),
    ]);

    // Act
    $response = $this->post(route('admin.sendifico.draft', $order->id));

    // Assert
    $response->assertSessionHas('success');

    expect(Shipment::where('order_id', $order->id)->first()->shipment_id)->toBe(4001234);
});

it('quotes the draft once the parcel is weighed and stores the rates', function () {
    // Arrange
    $order = $this->createOrderFor('Quito', 'Pichincha');

    Shipment::create([
        'order_id' => $order->id,
        'shipment_id' => 4001234,
        'status' => 'created',
    ]);

    Http::fake([
        'api.sendifico.com/api/public/shipment/4001234' => Http::response(draftPayload([
            'status' => 'quoted',
            'rates' => [
                ['rateId' => 7001, 'carrierToken' => 'ec_laar', 'priceTotal' => 1.01, 'currency' => 'USD', 'estimateDays' => 2, 'available' => true],
                ['rateId' => 7002, 'carrierToken' => 'ec_tramaco', 'priceTotal' => 1.10, 'currency' => 'USD', 'estimateDays' => 2, 'available' => false, 'unavailableReason' => 'codLimitExceeded'],
            ],
        ])),
    ]);

    // Act
    $response = $this->post(route('admin.sendifico.parcel', $order->id), [
        'weight' => 1.5,
        'length' => 30,
        'width' => 20,
        'height' => 10,
    ]);

    // Assert
    $response->assertSessionHas('success');

    $row = Shipment::where('order_id', $order->id)->first();

    expect($row->status)->toBe('quoted');

    expect($row->rates)->toHaveCount(2);

    expect($row->parcel['weight'])->toBe(1.5);

    Http::assertSent(fn ($request) => $request->data() === [
        'parcel' => ['weight' => 1.5, 'length' => 30.0, 'width' => 20.0, 'height' => 10.0],
    ]);
});

it('purchases the chosen rate from the wallet', function () {
    // Arrange
    $order = $this->createOrderFor('Quito', 'Pichincha');

    Shipment::create([
        'order_id' => $order->id,
        'shipment_id' => 4001234,
        'status' => 'quoted',
    ]);

    Http::fake([
        'api.sendifico.com/api/public/shipment/purchase/*' => Http::response(draftPayload([
            'status' => 'quoted',
            'isPaid' => true,
            'preferredCarrierToken' => 'ec_laar',
            'priceTotal' => 1.01,
        ])),
    ]);

    // Act
    $response = $this->post(route('admin.sendifico.purchase', $order->id), ['rate_id' => 7001]);

    // Assert
    $response->assertSessionHas('success');

    $row = Shipment::where('order_id', $order->id)->first();

    expect($row->is_paid)->toBeTrue();

    expect($row->carrier_token)->toBe('ec_laar');

    Http::assertSent(fn ($request) => $request->data() === [
        'preferredRateObjectId' => 7001,
        'purchaseWith' => 'walletAvailable',
    ]);
});

it('surfaces an actionable message when the wallet has no funds', function () {
    // Arrange
    $order = $this->createOrderFor('Quito', 'Pichincha');

    Shipment::create([
        'order_id' => $order->id,
        'shipment_id' => 4001234,
        'status' => 'quoted',
    ]);

    Http::fake([
        'api.sendifico.com/api/public/shipment/purchase/*' => Http::response([
            'statusCode' => 422,
            'message' => 'paymentIntentExecuteTransactionWalletInsufficientFunds',
        ], 422),
    ]);

    // Act
    $response = $this->post(route('admin.sendifico.purchase', $order->id), ['rate_id' => 7001]);

    // Assert
    $response->assertSessionHas('warning', trans('sendifico::app.api-errors.insufficient-funds'));

    expect(Shipment::where('order_id', $order->id)->first()->is_paid)->toBeFalse();
});

it('generates the tracking number and stores the carrier tracking link', function () {
    // Arrange
    $order = $this->createOrderFor('Quito', 'Pichincha');

    Shipment::create([
        'order_id' => $order->id,
        'shipment_id' => 4001234,
        'status' => 'quoted',
        'is_paid' => true,
    ]);

    Http::fake([
        'api.sendifico.com/api/public/shipment/generateTrackingNumber/*' => Http::response(draftPayload([
            'status' => 'labelCreated',
            'isPaid' => true,
            'trackingNumber' => 'LAAR-EC-987654321',
            'trackingCarrierUrl' => 'https://tracking.sendifico.com/LAAR-EC-987654321',
        ])),
    ]);

    // Act
    $response = $this->post(route('admin.sendifico.tracking', $order->id));

    // Assert
    $response->assertSessionHas('success');

    $row = Shipment::where('order_id', $order->id)->first();

    expect($row->tracking_number)->toBe('LAAR-EC-987654321');

    expect($row->tracking_carrier_url)->toBe('https://tracking.sendifico.com/LAAR-EC-987654321');

    expect($row->status)->toBe('labelCreated');
});

it('sends the browser to a freshly minted label url', function () {
    // Arrange
    $order = $this->createOrderFor('Quito', 'Pichincha');

    Shipment::create([
        'order_id' => $order->id,
        'shipment_id' => 4001234,
        'status' => 'labelCreated',
        'is_paid' => true,
        'tracking_number' => 'LAAR-EC-987654321',
    ]);

    Http::fake([
        'api.sendifico.com/api/public/shipment/generateLabelUrl/*' => Http::response([
            'payload' => [
                'type' => 'carrierDefault',
                'downloadUrl' => 'https://storage.example.com/label.pdf?sig=abc',
                'expiresAt' => '2026-09-06T00:00:00.000Z',
            ],
            'objectType' => 'shipmentLabel',
        ]),
    ]);

    // Act
    $response = $this->get(route('admin.sendifico.label', $order->id));

    // Assert
    $response->assertRedirect('https://storage.example.com/label.pdf?sig=abc');
});

it('annuls a shipment with a reason and marks it terminal', function () {
    // Arrange
    $order = $this->createOrderFor('Quito', 'Pichincha');

    Shipment::create([
        'order_id' => $order->id,
        'shipment_id' => 4001234,
        'status' => 'quoted',
        'is_paid' => true,
    ]);

    Http::fake([
        'api.sendifico.com/api/public/shipment/annulAndRefund/*' => Http::response(draftPayload([
            'status' => 'refunded',
            'isPaid' => true,
        ])),
    ]);

    // Act
    $response = $this->post(route('admin.sendifico.annul', $order->id), [
        'reason' => 'recipientCanceledOrder',
        'details' => 'Cliente cambió de opinión',
    ]);

    // Assert
    $response->assertSessionHas('success');

    $row = Shipment::where('order_id', $order->id)->first();

    expect($row->status)->toBe('refunded');

    expect($row->is_terminal)->toBeTrue();

    Http::assertSent(fn ($request) => $request->data() === [
        'annulReasonStatus' => 'recipientCanceledOrder',
        'annulReasonDetails' => 'Cliente cambió de opinión',
    ]);
});
