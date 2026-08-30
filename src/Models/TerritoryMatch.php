<?php

namespace Tapstar\Sendifico\Models;

use Illuminate\Database\Eloquent\Model;
use Tapstar\Sendifico\Contracts\TerritoryMatch as TerritoryMatchContract;

class TerritoryMatch extends Model implements TerritoryMatchContract
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sendifico_territory_matches';

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
