<?php

namespace Tapstar\Sendifico\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tapstar\Sendifico\Api\Client;
use Tapstar\Sendifico\Shipment\CarrierCatalog;
use Tapstar\Sendifico\Shipment\PayloadBuilder;
use Webkul\Core\Models\CoreConfig;

class RateQuoter
{
    /**
     * Where the identifier of the reusable quoting shipment is kept. It lives
     * in the configuration rather than the cache, so clearing caches or
     * deploying does not strand the draft at Sendifico.
     */
    public const SHIPMENT_KEY = 'sales.carriers.sendifico.quote_shipment_id';

    /**
     * How long one destination and parcel combination keeps its answer.
     */
    public const DEFAULT_CACHE_HOURS = 12;

    /**
     * The smallest parcel the couriers price.
     */
    public const MINIMUM_WEIGHT = 0.5;

    public function __construct(
        protected Client $client,
        protected TerritoryService $territoryService
    ) {}

    /**
     * What every courier would charge to carry this cart, as the storefront
     * shows them: name, logo, estimated days and price. The price is their
     * subtotal, because their total already carries IVA and the checkout adds
     * it once more. The quote never turns into a real shipment; one draft is
     * reused and re-quoted for all of them.
     *
     * An empty list means the couriers answered and none of them serves the
     * destination; null means we could not ask at all.
     *
     * @param  \Webkul\Checkout\Contracts\Cart  $cart
     * @return array<int, array<string, mixed>>|null
     */
    public function quote($cart): ?array
    {
        if (! $this->client->hasApiKey()) {
            return null;
        }

        $address = $cart->shipping_address;

        if (! $address) {
            return null;
        }

        $this->territoryService->refreshIfStale();

        $territory = $this->territoryService->resolve($address->state, $address->city);

        if (! $territory) {
            return [];
        }

        $weight = $this->weightFor($cart);

        $box = $this->boxFor($weight);

        $key = 'sendifico.rates.'.md5($territory.'|'.$weight.'|'.implode('x', $box));

        $hours = (int) (core()->getConfigData('sales.carriers.sendifico.quote_cache_hours') ?: self::DEFAULT_CACHE_HOURS);

        $rates = Cache::get($key);

        if ($rates !== null) {
            return $rates;
        }

        $rates = Cache::lock('sendifico.quote.lock', 30)->block(20, fn () => $this->fetch($territory, $weight, $box));

        if ($rates !== null) {
            Cache::put($key, $rates, now()->addHours(max($hours, 1)));
        }

        return $rates;
    }

    /**
     * Ask Sendifico, reusing the quoting draft when there is one.
     *
     * @param  array<int, float>  $box
     * @return array<int, array<string, mixed>>|null
     */
    protected function fetch(string $territory, float $weight, array $box): ?array
    {
        $parcel = [
            'weight' => $weight,
            'length' => $box[0],
            'width' => $box[1],
            'height' => $box[2],
        ];

        if ($shipmentId = $this->shipmentId()) {
            $response = $this->call(fn () => $this->client->editShipment($shipmentId, [
                'recipientAddress' => $this->recipient($territory),
                'parcel' => $parcel,
            ]));

            if ($response?->successful()) {
                return $this->ratesFrom($response);
            }

            $this->rememberShipment(null);
        }

        $response = $this->call(fn () => $this->client->createShipment($this->draft($territory, $parcel)));

        if (! $response?->successful()) {
            return null;
        }

        $this->rememberShipment($response->json('payload.shipmentId'));

        return $this->ratesFrom($response);
    }

    /**
     * Run one API call, reporting transport failures as no answer at all.
     */
    protected function call(callable $request): ?\Illuminate\Http\Client\Response
    {
        try {
            return $request();
        } catch (\Exception $e) {
            report($e);

            return null;
        }
    }

    /**
     * The rates carried by a quoting response.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function ratesFrom(\Illuminate\Http\Client\Response $response): array
    {
        return $this->normalize($response->json('payload.rates') ?? []);
    }

    /**
     * The draft every quote is asked against, if one is still on file.
     */
    protected function shipmentId(): ?int
    {
        $id = core()->getConfigData(self::SHIPMENT_KEY);

        return $id ? (int) $id : null;
    }

    /**
     * Remember, or forget, the draft quotes are asked against.
     */
    protected function rememberShipment(?int $shipmentId): void
    {
        CoreConfig::updateOrCreate(
            ['code' => self::SHIPMENT_KEY, 'channel_code' => null, 'locale_code' => null],
            ['value' => $shipmentId]
        );

        Cache::forget('core_config');
    }

    /**
     * The payload that opens the quoting draft.
     *
     * @param  array<string, float>  $parcel
     * @return array<string, mixed>
     */
    protected function draft(string $territory, array $parcel): array
    {
        return [
            'extId' => 'bagisto-quote-'.substr(md5((string) config('app.url')), 0, 8).'-'.Str::random(6),
            'integration' => [
                'platform' => PayloadBuilder::PLATFORM,
                'url' => rtrim((string) config('app.url'), '/'),
                'connectorVersion' => PayloadBuilder::CONNECTOR_VERSION,
            ],
            'senderAddressId' => (int) core()->getConfigData('sales.carriers.sendifico.sender_address_id'),
            'recipientAddress' => $this->recipient($territory),
            'contents' => [core()->getConfigData('sales.carriers.sendifico.content_category') ?: 'audioAndVideoAccessory'],
            'goodsCollection' => 0,
            'goodsCurrency' => 'USD',
            'parcel' => $parcel,
        ];
    }

    /**
     * A placeholder recipient: rates follow the destination territory, so no
     * customer data is sent while merely asking for prices.
     *
     * @return array<string, string>
     */
    protected function recipient(string $territory): array
    {
        return [
            'fullName' => trans('sendifico::app.shop.quote-recipient'),
            'streetLine1' => trans('sendifico::app.shop.quote-street'),
            'territoryBaseId' => $territory,
            'country' => Client::COUNTRY,
            'phone' => (string) (core()->getConfigData('sales.carriers.sendifico.quote_phone') ?: '0999999999'),
            'email' => (string) (core()->getConfigData('sales.carriers.sendifico.quote_email') ?: config('mail.from.address')),
        ];
    }

    /**
     * What the cart weighs, with a floor so an empty weight still prices.
     *
     * @param  \Webkul\Checkout\Contracts\Cart  $cart
     */
    public function weightFor($cart): float
    {
        $weight = $cart->items
            ->filter(fn ($item) => $item->getTypeInstance()->isStockable())
            ->sum(fn ($item) => (float) ($item->product?->weight ?? 0) * (int) $item->quantity);

        return round(max($weight, self::MINIMUM_WEIGHT), 2);
    }

    /**
     * The box the weight travels in, from the configured brackets of
     * "maximum kilos: length x width x height".
     *
     * @return array<int, float>
     */
    public function boxFor(float $weight): array
    {
        $configured = (string) core()->getConfigData('sales.carriers.sendifico.parcel_boxes');

        $boxes = [];

        foreach (preg_split('/\R/', $configured) as $line) {
            if (preg_match('/^\s*([\d.]+)\s*:\s*(\d+)\s*[x×]\s*(\d+)\s*[x×]\s*(\d+)\s*$/i', $line, $match)) {
                $boxes[] = [(float) $match[1], [(float) $match[2], (float) $match[3], (float) $match[4]]];
            }
        }

        if (empty($boxes)) {
            $boxes = [[1, [25, 20, 10]], [5, [35, 30, 20]], [20, [50, 40, 30]], [1000, [60, 50, 40]]];
        }

        usort($boxes, fn ($a, $b) => $a[0] <=> $b[0]);

        foreach ($boxes as [$maximum, $box]) {
            if ($weight <= $maximum) {
                return $box;
            }
        }

        return end($boxes)[1];
    }

    /**
     * Turn the API's rate list into what the storefront displays.
     *
     * @param  array<int, array<string, mixed>>  $rates
     * @return array<int, array<string, mixed>>
     */
    protected function normalize(array $rates): array
    {
        return collect($rates)
            ->filter(fn ($rate) => ($rate['available'] ?? false) && isset($rate['carrierToken']))
            ->map(fn ($rate) => [
                'token' => $rate['carrierToken'],
                'name' => CarrierCatalog::name($rate['carrierToken']),
                'logo' => CarrierCatalog::logo($rate['carrierToken']),
                'days' => (int) ($rate['estimateDays'] ?? 0),
                'price' => (float) ($rate['priceSubtotal'] ?? $rate['priceTotal'] ?? 0),
                'price_with_tax' => (float) ($rate['priceTotal'] ?? 0),
                'rate_id' => $rate['rateId'] ?? null,
            ])
            ->sortBy('price')
            ->values()
            ->all();
    }
}
