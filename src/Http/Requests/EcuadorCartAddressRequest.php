<?php

namespace Tapstar\Sendifico\Http\Requests;

use Tapstar\Sendifico\Rules\CedulaORuc;
use Webkul\Core\Rules\PostCode;
use Webkul\Shop\Http\Requests\CartAddressRequest;

class EcuadorCartAddressRequest extends CartAddressRequest
{
    /**
     * The store ships only inside Ecuador, and Ecuadorian invoicing needs a
     * valid cédula or RUC — so the country is pinned to EC and the billing
     * tax id is required and check-digit validated. Postal codes go unused in
     * Ecuador, so one is accepted but never demanded.
     */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['billing', 'shipping'] as $addressType) {
            if (isset($rules["{$addressType}.country"])) {
                $rules["{$addressType}.country"] = ['required', 'in:EC'];
            }

            if (isset($rules["{$addressType}.postcode"])) {
                $rules["{$addressType}.postcode"] = ['nullable', new PostCode];
            }
        }

        if ($this->has('billing')) {
            $rules['billing.vat_id'] = ['required', new CedulaORuc];
        }

        return $rules;
    }
}
