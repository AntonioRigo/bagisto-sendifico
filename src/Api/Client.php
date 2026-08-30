<?php

namespace Tapstar\Sendifico\Api;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tapstar\Sendifico\Repositories\LogRepository;

class Client
{
    /**
     * Base URL of the Sendifico public API.
     *
     * @var string
     */
    public const BASE_URL = 'https://api.sendifico.com/api/public';

    /**
     * Date-stamped API version this integration targets.
     *
     * @var string
     */
    public const API_VERSION = '2026-01-01';

    /**
     * The only market this store ships in.
     *
     * @var string
     */
    public const COUNTRY = 'EC';

    /**
     * Seconds to wait on a Sendifico API call before giving up.
     *
     * @var int
     */
    public const REQUEST_TIMEOUT = 30;

    /**
     * Create a new client instance.
     *
     * @return void
     */
    public function __construct(protected LogRepository $logRepository) {}

    /**
     * Get the configured API key.
     *
     * @return string|null
     */
    public function getApiKey()
    {
        return core()->getConfigData('sales.carriers.sendifico.api_key');
    }

    /**
     * Check whether the API credentials have been configured.
     *
     * @return bool
     */
    public function hasApiKey()
    {
        return ! empty($this->getApiKey());
    }

    /**
     * Fetch the full territory catalog for the country.
     */
    public function territories(): Response
    {
        return $this->request('GET', '/territory');
    }

    /**
     * List the account's saved addresses.
     */
    public function addresses(int $page = 1, int $limit = 100): Response
    {
        return $this->request('GET', '/address?'.http_build_query(['page' => $page, 'limit' => $limit]));
    }

    /**
     * Create a shipment draft.
     */
    public function createShipment(array $payload, ?int $orderId = null): Response
    {
        return $this->request('POST', '/shipment', $payload, $orderId);
    }

    /**
     * List shipments.
     */
    public function listShipments(int $page = 1, int $limit = 50): Response
    {
        return $this->request('GET', '/shipment?'.http_build_query(['page' => $page, 'limit' => $limit]));
    }

    /**
     * Retrieve a shipment.
     */
    public function getShipment(int $shipmentId, ?int $orderId = null): Response
    {
        return $this->request('GET', '/shipment/'.$shipmentId, null, $orderId);
    }

    /**
     * Edit an unpaid draft and re-quote it.
     */
    public function editShipment(int $shipmentId, array $payload, ?int $orderId = null): Response
    {
        return $this->request('PATCH', '/shipment/'.$shipmentId, $payload, $orderId);
    }

    /**
     * Pick a rate and pay for a shipment from the wallet.
     */
    public function purchaseShipment(int $shipmentId, int $rateId, string $purchaseWith, ?int $orderId = null): Response
    {
        return $this->request('PATCH', '/shipment/purchase/'.$shipmentId, [
            'preferredRateObjectId' => $rateId,
            'purchaseWith' => $purchaseWith,
        ], $orderId);
    }

    /**
     * Ask the carrier to accept the shipment and issue its tracking number.
     */
    public function generateTrackingNumber(int $shipmentId, ?int $orderId = null): Response
    {
        return $this->request('PATCH', '/shipment/generateTrackingNumber/'.$shipmentId, [], $orderId);
    }

    /**
     * Mint a short-lived download URL for the label PDF.
     */
    public function generateLabelUrl(int $shipmentId, ?int $orderId = null): Response
    {
        return $this->request('POST', '/shipment/generateLabelUrl/'.$shipmentId, [
            'type' => 'carrierDefault',
            'disposition' => 'inline',
        ], $orderId);
    }

    /**
     * Cancel a shipment and refund any payment.
     */
    public function annulAndRefund(int $shipmentId, string $reason, ?string $details = null, ?int $orderId = null): Response
    {
        $payload = ['annulReasonStatus' => $reason];

        if ($details) {
            $payload['annulReasonDetails'] = $details;
        }

        return $this->request('PATCH', '/shipment/annulAndRefund/'.$shipmentId, $payload, $orderId);
    }

    /**
     * Send an authenticated request to the Sendifico API and log the exchange.
     */
    protected function request(string $method, string $path, ?array $payload = null, ?int $orderId = null): Response
    {
        $pending = Http::withHeaders([
            'x-api-key' => (string) $this->getApiKey(),
            'x-sendifico-api-version' => self::API_VERSION,
            'x-sendifico-country' => self::COUNTRY,
        ])
            ->asJson()
            ->acceptJson()
            ->timeout(self::REQUEST_TIMEOUT);

        try {
            $response = $pending->send($method, self::BASE_URL.$path, $payload === null ? [] : ['json' => $payload]);
        } catch (\Exception $e) {
            $this->log($method, $path, $payload, null, $e->getMessage(), $orderId);

            throw $e;
        }

        $this->log($method, $path, $payload, $response->status(), $response->body(), $orderId);

        return $response;
    }

    /**
     * Record the exchange without ever letting logging break the call itself.
     */
    protected function log(string $method, string $path, ?array $payload, ?int $status, string $body, ?int $orderId): void
    {
        try {
            $this->logRepository->create([
                'order_id' => $orderId,
                'method' => $method,
                'url' => $path,
                'status' => $status,
                'request' => $payload ? json_encode($payload) : null,
                'response' => mb_substr($body, 0, 65000),
            ]);
        } catch (\Exception $e) {
            report($e);
        }
    }
}
