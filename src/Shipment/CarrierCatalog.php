<?php

namespace Tapstar\Sendifico\Shipment;

class CarrierCatalog
{
    /**
     * The couriers Sendifico quotes in Ecuador, with the artwork shipped
     * alongside this package.
     *
     * @var array<string, array<string, string>>
     */
    public const CARRIERS = [
        'ec_urbano' => ['name' => 'Urbano', 'logo' => 'ec-urbano-logo.png'],
        'ec_yobel' => ['name' => 'Yobel', 'logo' => 'ec-yobel-logo.png'],
        'ec_tramaco' => ['name' => 'Tramaco', 'logo' => 'ec-tramaco-logo.png'],
        'ec_gintracom' => ['name' => 'Gintracom', 'logo' => 'ec-gintracom-logo.png'],
        'ec_laar' => ['name' => 'Laar', 'logo' => 'ec-laar-logo.png'],
        'ec_servientrega' => ['name' => 'Servientrega', 'logo' => 'ec-servientrega-logo.jpg'],
        'servientrega' => ['name' => 'Servientrega', 'logo' => 'ec-servientrega-logo.jpg'],
        'ec_enviexpress' => ['name' => 'Envíexpress', 'logo' => 'ec-enviexpress-logo.png'],
        'ec_delivereo' => ['name' => 'Delivereo', 'logo' => 'ec-delivereo-logo.png'],
    ];

    /**
     * The courier's name in the capitals the storefront shows, falling back to
     * a readable form of its token.
     */
    public static function name(string $token): string
    {
        return mb_strtoupper(
            self::CARRIERS[$token]['name'] ?? str_replace(['ec_', '_'], ['', ' '], $token)
        );
    }

    /**
     * The URL the storefront loads the courier's logo from.
     */
    public static function logo(string $token): ?string
    {
        if (! isset(self::CARRIERS[$token])) {
            return null;
        }

        return route('shop.sendifico.carrier-logo', $token);
    }

    /**
     * Where the artwork lives on disk.
     */
    public static function logoPath(string $token): ?string
    {
        $file = self::CARRIERS[$token]['logo'] ?? null;

        if (! $file) {
            return null;
        }

        $path = dirname(__DIR__).'/Resources/assets/images/carriers/'.$file;

        return is_file($path) ? $path : null;
    }
}
