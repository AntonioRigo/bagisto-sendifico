<?php

use Tapstar\Sendifico\Carriers\Sendifico;

return [
    'sendifico' => [
        'code' => 'sendifico',
        'title' => 'Envío a domicilio',
        'description' => 'Entrega a domicilio en todo Ecuador',
        'active' => false,
        'default_rate' => '6',
        'free_threshold' => '25',
        'class' => Sendifico::class,
    ],
];
