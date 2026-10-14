<?php

use Illuminate\Support\Facades\Route;
use Tapstar\Sendifico\Http\Controllers\Shop\CarrierLogoController;
use Tapstar\Sendifico\Http\Controllers\Shop\TerritoryController;

Route::group(['middleware' => ['web']], function () {
    Route::get('sendifico/territories', [TerritoryController::class, 'index'])
        ->name('shop.sendifico.territories');

    Route::get('sendifico/carrier/{token}', [CarrierLogoController::class, 'show'])
        ->name('shop.sendifico.carrier-logo');
});
