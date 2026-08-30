<?php

namespace Tapstar\Sendifico\Models;

use Illuminate\Database\Eloquent\Model;
use Tapstar\Sendifico\Contracts\Shipment as ShipmentContract;
use Webkul\Sales\Models\OrderProxy;

class Shipment extends Model implements ShipmentContract
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sendifico_shipments';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_paid' => 'boolean',
        'is_terminal' => 'boolean',
        'parcel' => 'array',
        'rates' => 'array',
    ];

    /**
     * Get the order this shipment belongs to.
     */
    public function order()
    {
        return $this->belongsTo(OrderProxy::modelClass());
    }
}
