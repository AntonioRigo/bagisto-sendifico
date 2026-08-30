<?php

namespace Tapstar\Sendifico\Models;

use Illuminate\Database\Eloquent\Model;
use Tapstar\Sendifico\Contracts\Log as LogContract;

class Log extends Model implements LogContract
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sendifico_logs';

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
