<?php

namespace Tapstar\Sendifico\Http\Controllers\Shop;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tapstar\Sendifico\Shipment\CarrierCatalog;
use Webkul\Shop\Http\Controllers\Controller;

class CarrierLogoController extends Controller
{
    /**
     * Serve a courier's logo straight from the package, so the artwork needs
     * no publishing step on deploy.
     */
    public function show(string $token): BinaryFileResponse|Response
    {
        $path = CarrierCatalog::logoPath($token);

        if (! $path) {
            abort(404);
        }

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
