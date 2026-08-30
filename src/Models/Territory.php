<?php

namespace Tapstar\Sendifico\Models;

use Illuminate\Database\Eloquent\Model;
use Tapstar\Sendifico\Contracts\Territory as TerritoryContract;

class Territory extends Model implements TerritoryContract
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sendifico_territories';

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
}
