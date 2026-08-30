<?php

namespace Tapstar\Sendifico\Shipment;

use Tapstar\Sendifico\Support\PhoneNormalizer;
use Webkul\Sales\Contracts\Order;

class PayloadBuilder
{
    /**
     * Platform identifier sent in the integration object.
     *
     * @var string
     */
    public const PLATFORM = 'bagisto';

    /**
     * Version of this connector, reported to Sendifico for support purposes.
     *
     * @var string
     */
    public const CONNECTOR_VERSION = '1.0.0';

    /**
     * Build the shipment-create payload for an order.
     *
     * The parcel is always deferred: real weight and measurements are entered at packing
     * time, which quotes the draft for the first time.
     *
     * @param  Order  $order
     * @param  int  $senderAddressId
     * @param  string  $territoryBaseId
     * @param  string  $contentCategory
     * @param  float  $goodsCollection
     * @return array
     */
    public static function build($order, $senderAddressId, $territoryBaseId, $contentCategory, $goodsCollection)
    {
        $address = $order->shipping_address ?: $order->billing_address;

        $recipient = [
            'fullName' => trim($address->first_name.' '.$address->last_name),
            'streetLine1' => trim(preg_replace('/\r?\n+/', ', ', trim((string) $address->address)) ?? ''),
            'territoryBaseId' => $territoryBaseId,
            'country' => 'EC',
        ];

        if ($phone = PhoneNormalizer::toE164($address->phone)) {
            $recipient['phone'] = $phone;
        }

        if ($address->company_name) {
            $recipient['company'] = $address->company_name;
        }

        if ($email = $address->email ?: $order->customer_email) {
            $recipient['email'] = $email;
        }

        if ($address->postcode) {
            $recipient['zip'] = (string) $address->postcode;
        }

        return [
            'extId' => 'bagisto-'.$order->increment_id,
            'integration' => [
                'platform' => self::PLATFORM,
                'url' => rtrim((string) config('app.url'), '/'),
                'connectorVersion' => self::CONNECTOR_VERSION,
            ],
            'senderAddressId' => (int) $senderAddressId,
            'recipientAddress' => $recipient,
            'contents' => [$contentCategory],
            'goodsCollection' => $goodsCollection,
            'goodsCurrency' => 'USD',
        ];
    }
}
