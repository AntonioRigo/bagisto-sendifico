<?php

namespace Tapstar\Sendifico\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Tapstar\Sendifico\Contracts\Shipment;
use Tapstar\Sendifico\Repositories\ShipmentRepository;
use Tapstar\Sendifico\Services\OperationResult;
use Tapstar\Sendifico\Services\ShipmentService;
use Tapstar\Sendifico\Services\TerritoryService;
use Tapstar\Sendifico\Shipment\ShipmentStatus;
use Webkul\Sales\Repositories\OrderAddressRepository;
use Webkul\Sales\Repositories\OrderRepository;

class ShipmentController extends Controller
{
    /**
     * Annul reasons the API accepts.
     *
     * @var string[]
     */
    public const ANNUL_REASONS = [
        'incorrectAddress',
        'incorrectShipmentDetails',
        'fullfilmentIssue',
        'recipientRefusedDelivery',
        'recipientNotAvailable',
        'recipientCanceledOrder',
        'platformCost',
        'deliveryDelay',
        'carrierOutOfCoverageArea',
        'carrierOutOfOfficeSchedule',
        'other',
    ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected OrderAddressRepository $orderAddressRepository,
        protected ShipmentRepository $shipmentRepository,
        protected ShipmentService $shipmentService,
        protected TerritoryService $territoryService,
    ) {}

    /**
     * Create the Sendifico draft for the order.
     *
     * @param  int  $orderId
     * @return RedirectResponse
     */
    public function draft($orderId)
    {
        $order = $this->orderRepository->findOrFail($orderId);

        return $this->redirectWith($this->shipmentService->createDraft($order));
    }

    /**
     * Store the operator's territory choice for this address, then retry the draft.
     *
     * @param  int  $orderId
     * @return RedirectResponse
     */
    public function confirmTerritory($orderId)
    {
        $validated = request()->validate([
            'territory_base_id' => 'required|string|exists:sendifico_territories,territory_base_id',
        ]);

        $order = $this->orderRepository->findOrFail($orderId);

        $address = $order->shipping_address ?: $order->billing_address;

        $this->territoryService->confirm($address->state, $address->city, $validated['territory_base_id']);

        return $this->redirectWith($this->shipmentService->syncRecipient($order));
    }

    /**
     * Correct the recipient address the shipment is built from, then push the
     * correction onto the draft when one already exists.
     *
     * @param  int  $orderId
     * @return RedirectResponse
     */
    public function updateAddress($orderId)
    {
        $order = $this->orderRepository->findOrFail($orderId);

        $row = $this->shipmentRepository->findOneWhere(['order_id' => $order->id]);

        if (
            $row
            && ($row->is_paid || $row->is_terminal)
        ) {
            session()->flash('error', trans('sendifico::app.result.recipient-locked'));

            return redirect()->back();
        }

        $validated = request()->validate([
            'first_name' => 'required|string|max:191',
            'last_name'  => 'required|string|max:191',
            'address'    => 'required|string|max:500',
            'city'       => 'required|string|max:191',
            'state'      => 'required|string|max:191',
            'postcode'   => 'nullable|string|max:20',
            'phone'      => 'required|string|max:30',
        ]);

        $address = $order->shipping_address ?: $order->billing_address;

        if (! $address) {
            session()->flash('error', trans('sendifico::app.result.address-missing'));

            return redirect()->back();
        }

        $this->orderAddressRepository->update($validated, $address->id);

        if (! $row) {
            session()->flash('success', trans('sendifico::app.result.address-updated'));

            return redirect()->back();
        }

        return $this->redirectWith($this->shipmentService->syncRecipient($order->refresh()));
    }

    /**
     * Store the packed parcel and quote the draft.
     *
     * @param  int  $orderId
     * @return RedirectResponse
     */
    public function parcel($orderId)
    {
        $validated = request()->validate([
            'weight' => 'required|numeric|min:1',
            'length' => 'required|numeric|min:1',
            'width' => 'required|numeric|min:1',
            'height' => 'required|numeric|min:1',
        ]);

        return $this->redirectWith($this->shipmentService->completeParcel(
            $this->rowFor($orderId),
            (float) $validated['weight'],
            (float) $validated['length'],
            (float) $validated['width'],
            (float) $validated['height']
        ));
    }

    /**
     * Pay for the shipment with the chosen rate.
     *
     * @param  int  $orderId
     * @return RedirectResponse
     */
    public function purchase($orderId)
    {
        $validated = request()->validate([
            'rate_id' => 'required|integer',
        ]);

        return $this->redirectWith($this->shipmentService->purchase(
            $this->rowFor($orderId),
            (int) $validated['rate_id']
        ));
    }

    /**
     * Generate the carrier tracking number.
     *
     * @param  int  $orderId
     * @return RedirectResponse
     */
    public function tracking($orderId)
    {
        return $this->redirectWith($this->shipmentService->generateTracking($this->rowFor($orderId)));
    }

    /**
     * Mint a fresh label URL and send the browser to it.
     *
     * @param  int  $orderId
     * @return RedirectResponse
     */
    public function label($orderId)
    {
        $result = $this->shipmentService->labelUrl($this->rowFor($orderId));

        if (
            $result->success
            && ! empty($result->data['url'])
        ) {
            return redirect()->away($result->data['url']);
        }

        return $this->redirectWith($result);
    }

    /**
     * Cancel the shipment and refund any payment.
     *
     * @param  int  $orderId
     * @return RedirectResponse
     */
    public function annul($orderId)
    {
        $validated = request()->validate([
            'reason' => 'required|in:'.implode(',', self::ANNUL_REASONS),
            'details' => 'nullable|string|max:255',
        ]);

        return $this->redirectWith($this->shipmentService->annul(
            $this->rowFor($orderId),
            $validated['reason'],
            $validated['details'] ?? null
        ));
    }

    /**
     * Re-read the shipment's current state from Sendifico.
     *
     * @param  int  $orderId
     * @return RedirectResponse
     */
    public function refresh($orderId)
    {
        return $this->redirectWith($this->shipmentService->refresh($this->rowFor($orderId)));
    }

    /**
     * Get the local Sendifico row for an order, requiring a created draft.
     *
     * @param  int  $orderId
     * @return Shipment
     */
    protected function rowFor($orderId)
    {
        $row = $this->shipmentRepository->findOneWhere(['order_id' => $orderId]);

        abort_if(
            ! $row
            || ! $row->shipment_id
            || $row->status === ShipmentStatus::TERRITORY_PENDING,
            404
        );

        return $row;
    }

    /**
     * Send the operator back to the order with the result's message.
     *
     * @return RedirectResponse
     */
    protected function redirectWith(OperationResult $result)
    {
        session()->flash($result->success ? 'success' : 'warning', $result->message);

        return redirect()->back();
    }
}
