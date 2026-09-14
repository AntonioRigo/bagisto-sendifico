<?php

$categories = [
    'agriculturalSupply', 'audioAndVideoAccessory', 'babyProduct', 'beverage', 'book',
    'camera', 'cellularPhone', 'clothes', 'clothingAccessory', 'computer', 'decoration',
    'documents', 'dron', 'electromenor', 'food', 'householdAppliance', 'jewelryAndWatch',
    'kitchenware', 'medicalEquipment', 'medicalSupply', 'musicalInstrument', 'party',
    'personalCareProduct', 'petAccessory', 'petFood', 'printer', 'shoeAndFootwear',
    'spareparts', 'sportingArticle', 'stationery', 'television', 'toy', 'vehicleAccessory',
    'videoGame',
];

return [
    [
        'key' => 'sales.carriers.sendifico',
        'name' => 'sendifico::app.admin.config.page-title',
        'info' => 'sendifico::app.admin.config.page-info',
        'sort' => 5,
        'fields' => [
            [
                'name' => 'active',
                'title' => 'sendifico::app.admin.config.status',
                'type' => 'boolean',
                'channel_based' => true,
                'locale_based' => false,
            ], [
                'name' => 'title',
                'title' => 'sendifico::app.admin.config.method-title',
                'type' => 'text',
                'depends' => 'active:1',
                'validation' => 'required_if:active,1',
                'channel_based' => true,
                'locale_based' => true,
            ], [
                'name' => 'description',
                'title' => 'sendifico::app.admin.config.method-description',
                'type' => 'textarea',
                'depends' => 'active:1',
                'channel_based' => true,
                'locale_based' => true,
            ], [
                'name' => 'default_rate',
                'title' => 'sendifico::app.admin.config.rate',
                'info' => 'sendifico::app.admin.config.rate-info',
                'type' => 'text',
                'depends' => 'active:1',
                'validation' => 'required_if:active,1|numeric|min:0',
                'channel_based' => true,
                'locale_based' => false,
            ], [
                'name' => 'rate_mode',
                'title' => 'sendifico::app.admin.config.rate-mode',
                'info' => 'sendifico::app.admin.config.rate-mode-info',
                'type' => 'select',
                'default' => 'per_order',
                'depends' => 'active:1',
                'options' => [
                    [
                        'title' => 'sendifico::app.admin.config.rate-mode-order',
                        'value' => 'per_order',
                    ], [
                        'title' => 'sendifico::app.admin.config.rate-mode-item',
                        'value' => 'per_item',
                    ],
                ],
                'channel_based' => true,
                'locale_based' => false,
            ], [
                'name' => 'free_threshold',
                'title' => 'sendifico::app.admin.config.free-threshold',
                'info' => 'sendifico::app.admin.config.free-threshold-info',
                'type' => 'text',
                'depends' => 'active:1',
                'validation' => 'numeric|min:0',
                'channel_based' => true,
                'locale_based' => false,
            ], [
                'name' => 'province_surcharges',
                'title' => 'sendifico::app.admin.config.province-surcharges',
                'info' => 'sendifico::app.admin.config.province-surcharges-info',
                'type' => 'textarea',
                'depends' => 'active:1',
                'channel_based' => true,
                'locale_based' => false,
            ], [
                'name' => 'api_key',
                'title' => 'sendifico::app.admin.config.api-key',
                'info' => 'sendifico::app.admin.config.api-key-info',
                'type' => 'password',
                'channel_based' => false,
                'locale_based' => false,
            ], [
                'name' => 'sender_address_id',
                'title' => 'sendifico::app.admin.config.sender-address-id',
                'info' => 'sendifico::app.admin.config.sender-address-id-info',
                'type' => 'text',
                'validation' => 'integer',
                'channel_based' => false,
                'locale_based' => false,
            ], [
                'name' => 'content_category',
                'title' => 'sendifico::app.admin.config.content-category',
                'info' => 'sendifico::app.admin.config.content-category-info',
                'type' => 'select',
                'default' => 'audioAndVideoAccessory',
                'options' => array_map(
                    fn (string $category) => ['title' => $category, 'value' => $category],
                    $categories
                ),
                'channel_based' => false,
                'locale_based' => false,
            ], [
                'name' => 'purchase_with',
                'title' => 'sendifico::app.admin.config.purchase-with',
                'info' => 'sendifico::app.admin.config.purchase-with-info',
                'type' => 'select',
                'default' => 'walletAvailable',
                'options' => [
                    [
                        'title' => 'sendifico::app.admin.config.purchase-with-available',
                        'value' => 'walletAvailable',
                    ], [
                        'title' => 'sendifico::app.admin.config.purchase-with-tokenized',
                        'value' => 'walletTokenized',
                    ],
                ],
                'channel_based' => false,
                'locale_based' => false,
            ], [
                'name' => 'cod_enabled',
                'title' => 'sendifico::app.admin.config.cod-enabled',
                'info' => 'sendifico::app.admin.config.cod-enabled-info',
                'type' => 'boolean',
                'channel_based' => false,
                'locale_based' => false,
            ], [
                'name' => 'cod_mode',
                'title' => 'sendifico::app.admin.config.cod-mode',
                'info' => 'sendifico::app.admin.config.cod-mode-info',
                'type' => 'select',
                'default' => 'total',
                'depends' => 'cod_enabled:1',
                'options' => [
                    [
                        'title' => 'sendifico::app.admin.config.cod-mode-total',
                        'value' => 'total',
                    ], [
                        'title' => 'sendifico::app.admin.config.cod-mode-products',
                        'value' => 'products_only',
                    ],
                ],
                'channel_based' => false,
                'locale_based' => false,
            ], [
                'name' => 'auto_draft',
                'title' => 'sendifico::app.admin.config.auto-draft',
                'info' => 'sendifico::app.admin.config.auto-draft-info',
                'type' => 'select',
                'default' => 'manual',
                'options' => [
                    [
                        'title' => 'sendifico::app.admin.config.auto-draft-manual',
                        'value' => 'manual',
                    ], [
                        'title' => 'sendifico::app.admin.config.auto-draft-on-order',
                        'value' => 'on_order_save',
                    ],
                ],
                'channel_based' => false,
                'locale_based' => false,
            ],
        ],
    ],
];
