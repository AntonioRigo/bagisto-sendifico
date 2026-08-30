<?php

namespace Tapstar\Sendifico\Services;

use Illuminate\Http\Client\Response;
use Tapstar\Sendifico\Api\Client;
use Tapstar\Sendifico\Api\ErrorCatalog;
use Tapstar\Sendifico\Contracts\Shipment;
use Tapstar\Sendifico\Repositories\ShipmentRepository;
use Tapstar\Sendifico\Shipment\CodCalculator;
use Tapstar\Sendifico\Shipment\PayloadBuilder;
use Tapstar\Sendifico\Shipment\ShipmentStatus;
use Webkul\Sales\Contracts\Order;

class ShipmentService
{
    /**
     * Pages of the shipment list searched when recovering from a duplicate external id.
     *
     * @var int
     */
    public const RECOVERY_PAGES = 5;

    /**
     * Create a new service instance.
     *
     * @return void
     */
    public function __construct(
        protected Client $client,
        protected ShipmentRepository $shipmentRepository,
        protected TerritoryService $territoryService,
    ) {}

    /**
     * Create a Sendifico draft for an order, with the parcel deferred to packing time.
     *
     * @param  Order  $order
     * @return OperationResult
     */
    public function createDraft($order)
    {
        $row = $this->shipmentRepository->findOneWhere(['order_id' => $order->id]);

        if ($row?->shipment_id) {
            return OperationResult::success(trans('sendifico::app.result.draft-exists', ['id' => $row->shipment_id]));
        }

        if (! $this->client->hasApiKey()) {
            return OperationResult::failure(trans('sendifico::app.result.not-configured'));
        }

        $senderAddressId = core()->getConfigData('sales.carriers.sendifico.sender_address_id');

        if (! $senderAddressId) {
            return OperationResult::failure(trans('sendifico::app.api-errors.sender-missing'));
        }

        $this->territoryService->refreshIfStale();

        $address = $order->shipping_address ?: $order->billing_address;

        $territoryBaseId = $this->territoryService->resolve($address->state, $address->city);

        if (! $territoryBaseId) {
            $this->upsertRow($order->id, $row, ['status' => ShipmentStatus::TERRITORY_PENDING]);

            return OperationResult::needsTerritory(trans('sendifico::app.result.territory-pending'));
        }

        $payload = PayloadBuilder::build(
            $order,
            (int) $senderAddressId,
            $territoryBaseId,
            core()->getConfigData('sales.carriers.sendifico.content_category') ?: 'audioAndVideoAccessory',
            $this->goodsCollectionFor($order)
        );

        try {
            $response = $this->client->createShipment($payload, $order->id);
        } catch (\Exception $e) {
            report($e);

            $this->upsertRow($order->id, $row, [
                'status' => $row->status ?? ShipmentStatus::TERRITORY_PENDING,
                'territory_base_id' => $territoryBaseId,
                'last_error' => trans('sendifico::app.result.transport-error'),
            ]);

            return OperationResult::failure(trans('sendifico::app.result.transport-error'));
        }

        if (ErrorCatalog::failedWith($response, 'pApiShipmentExtIdAlreadyUsed')) {
            return $this->recoverExistingDraft($order, $row, $payload['extId'], $territoryBaseId);
        }

        if ($response->failed()) {
            $message = ErrorCatalog::message($response);

            $this->upsertRow($order->id, $row, [
                'territory_base_id' => $territoryBaseId,
                'last_error' => $message,
            ]);

            return OperationResult::failure($message);
        }

        $this->persistFromApi($order->id, $row, $response->json('payload') ?: [], [
            'territory_base_id' => $territoryBaseId,
            'goods_collection' => $payload['goodsCollection'],
        ]);

        return OperationResult::success(trans('sendifico::app.result.draft-created'));
    }

    /**
     * Push the order's current address and territory onto the Sendifico draft.
     *
     * With no remote draft yet this is simply a fresh draft attempt, which
     * reads the corrected data on its own. A paid or terminal shipment can no
     * longer be corrected: the carrier already has it.
     *
     * @param  \Webkul\Sales\Contracts\Order  $order
     * @return OperationResult
     */
    public function syncRecipient($order)
    {
        $row = $this->shipmentRepository->findOneWhere(['order_id' => $order->id]);

        if (! $row?->shipment_id) {
            return $this->createDraft($order);
        }

        if ($row->is_paid || $row->is_terminal) {
            return OperationResult::failure(trans('sendifico::app.result.recipient-locked'));
        }

        $address = $order->shipping_address ?: $order->billing_address;

        $territoryBaseId = $this->territoryService->resolve($address->state, $address->city);

        if (! $territoryBaseId) {
            $this->upsertRow($order->id, $row, ['status' => ShipmentStatus::TERRITORY_PENDING]);

            return OperationResult::needsTerritory(trans('sendifico::app.result.territory-pending'));
        }

        try {
            $response = $this->client->editShipment((int) $row->shipment_id, [
                'recipientAddress' => PayloadBuilder::recipient($order, $territoryBaseId),
            ], $order->id);
        } catch (\Exception $e) {
            report($e);

            return OperationResult::failure(trans('sendifico::app.result.transport-error'));
        }

        if ($response->failed()) {
            $message = ErrorCatalog::message($response);

            $this->upsertRow($order->id, $row, ['last_error' => $message]);

            return OperationResult::failure($message);
        }

        $this->upsertRow($order->id, $row, [
            'territory_base_id' => $territoryBaseId,
            'last_error' => null,
        ]);

        return OperationResult::success(trans('sendifico::app.result.recipient-updated'));
    }

    /**
     * Store the packed parcel's real measurements and quote the draft.
     *
     * @param  Shipment  $row
     * @return OperationResult
     */
    public function completeParcel($row, float $weight, float $length, float $width, float $height)
    {
        $parcel = [
            'weight' => $weight,
            'length' => $length,
            'width' => $width,
            'height' => $height,
        ];

        return $this->call(
            fn () => $this->client->editShipment($row->shipment_id, ['parcel' => $parcel], $row->order_id),
            $row,
            trans('sendifico::app.result.quoted'),
            ['parcel' => $parcel]
        );
    }

    /**
     * Pay for the shipment with the chosen rate.
     *
     * @param  Shipment  $row
     * @return OperationResult
     */
    public function purchase($row, int $rateId)
    {
        $purchaseWith = core()->getConfigData('sales.carriers.sendifico.purchase_with') ?: 'walletAvailable';

        return $this->call(
            fn () => $this->client->purchaseShipment($row->shipment_id, $rateId, $purchaseWith, $row->order_id),
            $row,
            trans('sendifico::app.result.purchased')
        );
    }

    /**
     * Ask the carrier to accept the shipment and issue the tracking number.
     *
     * @param  Shipment  $row
     * @return OperationResult
     */
    public function generateTracking($row)
    {
        $result = $this->call(
            fn () => $this->client->generateTrackingNumber($row->shipment_id, $row->order_id),
            $row,
            trans('sendifico::app.result.tracking-created')
        );

        if (
            $result->success
            && ! $this->shipmentRepository->find($row->id)?->tracking_number
        ) {
            return OperationResult::failure(trans('sendifico::app.result.tracking-missing'));
        }

        return $result;
    }

    /**
     * Mint a fresh label download URL.
     *
     * @param  Shipment  $row
     * @return OperationResult
     */
    public function labelUrl($row)
    {
        try {
            $response = $this->client->generateLabelUrl($row->shipment_id, $row->order_id);
        } catch (\Exception $e) {
            report($e);

            return OperationResult::failure(trans('sendifico::app.result.transport-error'));
        }

        if ($response->failed()) {
            return OperationResult::failure(ErrorCatalog::message($response));
        }

        return OperationResult::success(trans('sendifico::app.result.label-ready'), [
            'url' => $response->json('payload.downloadUrl'),
        ]);
    }

    /**
     * Cancel the shipment and refund any payment.
     *
     * @param  Shipment  $row
     * @return OperationResult
     */
    public function annul($row, string $reason, ?string $details = null)
    {
        return $this->call(
            fn () => $this->client->annulAndRefund($row->shipment_id, $reason, $details, $row->order_id),
            $row,
            trans('sendifico::app.result.annulled')
        );
    }

    /**
     * Re-read the shipment from Sendifico and store its current state.
     *
     * @param  Shipment  $row
     * @return OperationResult
     */
    public function refresh($row)
    {
        return $this->call(
            fn () => $this->client->getShipment($row->shipment_id, $row->order_id),
            $row,
            trans('sendifico::app.result.refreshed')
        );
    }

    /**
     * Run an API call against an existing draft and persist whatever comes back.
     *
     * @param  Shipment  $row
     * @return OperationResult
     */
    protected function call(\Closure $request, $row, string $successMessage, array $extra = [])
    {
        try {
            $response = $request();
        } catch (\Exception $e) {
            report($e);

            return OperationResult::failure(trans('sendifico::app.result.transport-error'));
        }

        if ($response->failed()) {
            $message = ErrorCatalog::message($response);

            $this->shipmentRepository->update(['last_error' => $message], $row->id);

            return OperationResult::failure($message);
        }

        $this->persistFromApi($row->order_id, $row, $response->json('payload') ?: [], $extra);

        return OperationResult::success($successMessage);
    }

    /**
     * Map an API shipment payload onto the local row.
     *
     * The plain shipment detail carries no rate list, so the stored rates survive
     * until a draft response replaces them.
     *
     * @param  Shipment|null  $row
     */
    protected function persistFromApi(int $orderId, $row, array $payload, array $extra = []): void
    {
        $status = (string) ($payload['status'] ?? 'created');

        $attributes = array_merge([
            'shipment_id' => $payload['shipmentId'] ?? null,
            'ext_id' => $payload['extId'] ?? null,
            'status' => $status,
            'is_paid' => (bool) ($payload['isPaid'] ?? false),
            'is_terminal' => ShipmentStatus::isTerminal($status),
            'carrier_token' => $payload['preferredCarrierToken'] ?? null,
            'price_total' => $payload['priceTotal'] ?? null,
            'tracking_number' => $payload['trackingNumber'] ?? null,
            'tracking_carrier_url' => $payload['trackingCarrierUrl'] ?? null,
            'incident_count' => $payload['incidentCount'] ?? null,
            'last_error' => null,
        ], $extra);

        if (array_key_exists('rates', $payload)) {
            $attributes['rates'] = $payload['rates'];
        }

        $this->upsertRow($orderId, $row, $attributes);
    }

    /**
     * Create or update the local row for an order.
     *
     * @param  Shipment|null  $row
     */
    protected function upsertRow(int $orderId, $row, array $attributes): void
    {
        if ($row) {
            $this->shipmentRepository->update($attributes, $row->id);

            return;
        }

        $this->shipmentRepository->create(array_merge(['order_id' => $orderId], $attributes));
    }

    /**
     * The external id was already used: find that shipment and adopt it locally.
     *
     * @param  Order  $order
     * @param  Shipment|null  $row
     * @return OperationResult
     */
    protected function recoverExistingDraft($order, $row, string $extId, string $territoryBaseId)
    {
        for ($page = 1; $page <= self::RECOVERY_PAGES; $page++) {
            try {
                $response = $this->client->listShipments($page);
            } catch (\Exception $e) {
                report($e);

                break;
            }

            $items = $response->json('payload.data') ?: [];

            foreach ($items as $item) {
                if (($item['extId'] ?? null) === $extId) {
                    $this->persistFromApi($order->id, $row, $item, ['territory_base_id' => $territoryBaseId]);

                    return OperationResult::success(trans('sendifico::app.result.draft-recovered'));
                }
            }

            if (count($items) < 50) {
                break;
            }
        }

        return OperationResult::failure(trans('sendifico::app.result.recovery-failed', ['extId' => $extId]));
    }

    /**
     * Calculate the cash-on-delivery collection for an order.
     */
    protected function goodsCollectionFor($order): float
    {
        return CodCalculator::calculate(
            (bool) core()->getConfigData('sales.carriers.sendifico.cod_enabled'),
            $order->payment?->method === 'cashondelivery',
            core()->getConfigData('sales.carriers.sendifico.cod_mode') ?: CodCalculator::MODE_TOTAL,
            (float) $order->base_grand_total,
            (float) $order->base_shipping_amount
        );
    }
}
