<?php

use Illuminate\Support\Facades\Route;
use Tapstar\Sendifico\Http\Controllers\Admin\ShipmentController;

Route::group(['middleware' => ['web', 'admin'], 'prefix' => config('app.admin_url')], function () {
    Route::controller(ShipmentController::class)
        ->prefix('sales/orders/{order_id}/sendifico')
        ->group(function () {
            Route::post('draft', 'draft')->name('admin.sendifico.draft');

            Route::post('territory', 'confirmTerritory')->name('admin.sendifico.territory');

            Route::post('address', 'updateAddress')->name('admin.sendifico.address');

            Route::post('parcel', 'parcel')->name('admin.sendifico.parcel');

            Route::post('purchase', 'purchase')->name('admin.sendifico.purchase');

            Route::post('tracking', 'tracking')->name('admin.sendifico.tracking');

            Route::get('label', 'label')->name('admin.sendifico.label');

            Route::post('annul', 'annul')->name('admin.sendifico.annul');

            Route::post('refresh', 'refresh')->name('admin.sendifico.refresh');
        });
});
