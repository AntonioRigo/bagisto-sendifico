<?php

namespace Tapstar\Sendifico\Tests;

use Tapstar\Sendifico\Repositories\TerritoryRepository;
use Tests\TestCase;
use Webkul\Admin\Tests\Concerns\AdminTestBench;
use Webkul\Attribute\Models\Attribute;
use Webkul\Core\Models\CoreConfig;
use Webkul\Payment\Tests\Concerns\ProvidePaymentHelpers;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductAttributeValue;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderAddress;
use Webkul\Sales\Models\OrderPayment;

class SendificoTestCase extends TestCase
{
    use AdminTestBench, ProvidePaymentHelpers;

    /**
     * Store a Sendifico configuration value.
     */
    public function setSendificoConfig(string $field, string $value): void
    {
        CoreConfig::factory()->create([
            'code' => 'sales.carriers.sendifico.'.$field,
            'value' => $value,
            'channel_code' => 'default',
        ]);
    }

    /**
     * Store the base Sendifico configuration every flow test needs.
     */
    public function configureSendifico(array $overrides = []): void
    {
        foreach (array_merge([
            'active' => '1',
            'default_rate' => '6',
            'free_threshold' => '25',
            'api_key' => 'test-key',
            'sender_address_id' => '2001234',
            'content_category' => 'cellularPhone',
        ], $overrides) as $field => $value) {
            $this->setSendificoConfig($field, $value);
        }
    }

    /**
     * Mark a product for free shipping through its "Free shipping" toggle.
     */
    public function markFreeShipping(Product $product): void
    {
        $attribute = Attribute::query()->where('code', 'free_shipping')->firstOrFail();

        ProductAttributeValue::query()->updateOrCreate([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'channel' => null,
            'locale' => null,
        ], [
            'boolean_value' => 1,
            'unique_id' => $product->id.'|'.$attribute->id,
        ]);
    }

    /**
     * Cache a small Ecuadorian territory catalog locally.
     */
    public function seedTerritories(): void
    {
        app(TerritoryRepository::class)->replaceForCountry('EC', [
            [
                'territoryBaseId' => 'EC|:|PICHINCHA|:|QUITO|:|QUITO',
                'territory1Name' => 'PICHINCHA',
                'territory2Name' => 'QUITO',
                'territory3Name' => 'QUITO',
                'searchableText' => 'Pichincha - Quito - Quito',
            ],
            [
                'territoryBaseId' => 'EC|:|PICHINCHA|:|QUITO|:|TUMBACO',
                'territory1Name' => 'PICHINCHA',
                'territory2Name' => 'QUITO',
                'territory3Name' => 'TUMBACO',
                'searchableText' => 'Pichincha - Quito - Tumbaco',
            ],
            [
                'territoryBaseId' => 'EC|:|GUAYAS|:|GUAYAQUIL|:|GUAYAQUIL',
                'territory1Name' => 'GUAYAS',
                'territory2Name' => 'GUAYAQUIL',
                'territory3Name' => 'GUAYAQUIL',
                'searchableText' => 'Guayas - Guayaquil - Guayaquil',
            ],
        ]);
    }

    /**
     * Create an order with a shipping address and payment for the flow tests.
     */
    public function createOrderFor(string $city, string $state = '', string $paymentMethod = 'payphone'): Order
    {
        $order = Order::factory()->create([
            'status' => 'processing',
            'customer_email' => 'maria@example.com',
        ]);

        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'address_type' => OrderAddress::ADDRESS_TYPE_SHIPPING,
            'first_name' => 'María',
            'last_name' => 'García',
            'address' => 'Av. Amazonas N24-03',
            'city' => $city,
            'state' => $state,
            'country' => 'EC',
            'phone' => '+593 99 123 4567',
        ]);

        OrderPayment::factory()->create([
            'order_id' => $order->id,
            'method' => $paymentMethod,
        ]);

        return $order->fresh();
    }
}
