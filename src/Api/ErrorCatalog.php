<?php

namespace Tapstar\Sendifico\Api;

use Illuminate\Http\Client\Response;

class ErrorCatalog
{
    /**
     * API error codes mapped to the translation key of an actionable operator message.
     *
     * @var array
     */
    protected const MESSAGES = [
        'missingApiVersion' => 'config-invalid',
        'invalidApiVersion' => 'config-invalid',
        'unknownApiVersion' => 'config-invalid',
        'pApiCountryMissing' => 'config-invalid',
        'pApiCountryUnsupported' => 'config-invalid',
        'invalidApiKey' => 'auth-invalid',
        'pApiAddressSenderRequired' => 'sender-missing',
        'pApiAddressSenderNotFound' => 'sender-missing',
        'pApiShipmentContentsRequired' => 'contents-missing',
        'pApiQuotationParcelBadRequest' => 'parcel-invalid',
        'pApiShipmentParcelPartiallyDeferred' => 'parcel-invalid',
        'pApiShipmentGoodsCollectionOutOfRange' => 'cod-out-of-range',
        'pApiShipmentGoodsInsuredOutOfRange' => 'insured-out-of-range',
        'pApiShipmentExtIdAlreadyUsed' => 'ext-id-used',
        'paymentIntentExecuteTransactionWalletInsufficientFunds' => 'insufficient-funds',
        'paymentIntentShipmentPurchaseTransactionAlreadyCreated' => 'already-paid',
        'pApiShipmentPreferredRateRequired' => 'rate-required',
        'pApiShipmentRateNotFound' => 'rate-not-found',
        'ecGintracomShipmentCollectionMustEqualInsured' => 'gintracom-collection',
        'pApiShipmentNotEditable' => 'not-editable',
        'pApiShipmentNotFound' => 'shipment-not-found',
        'shipmentCarrierTokenNotFound' => 'not-purchased',
        'pApiShipmentLabelNotReady' => 'label-not-ready',
        'pApiShipmentLabelTypeNotFound' => 'label-not-ready',
        'shipmentAnnulAlreadyDone' => 'annul-done',
    ];

    /**
     * Translate a failed response into an actionable operator message.
     *
     * @return string
     */
    public static function message(Response $response)
    {
        $code = (string) $response->json('message');

        if (isset(self::MESSAGES[$code])) {
            return trans('sendifico::app.api-errors.'.self::MESSAGES[$code]);
        }

        return trans('sendifico::app.api-errors.unknown', [
            'code' => $code !== '' ? $code : 'HTTP '.$response->status(),
        ]);
    }

    /**
     * Check whether the response failed with the given API error code.
     *
     * @return bool
     */
    public static function failedWith(Response $response, string $code)
    {
        return $response->failed() && $response->json('message') === $code;
    }
}
