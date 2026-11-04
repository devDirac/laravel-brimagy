<?php

use App\Services\AcumularEnPendientes;
use App\Services\EnviarCotizacionDirecta;

return [
    'cotizacion_multiple' => [
        // acumular: duplica la validación al otro proveedor y queda en sus pendientes
        // directo:  duplica y además genera/envía la cotización en ese momento
        'modo' => env('OC_COTIZACION_MULTIPLE', 'acumular'),

        'estrategias' => [
            'acumular' => AcumularEnPendientes::class,
            'directo' => EnviarCotizacionDirecta::class,
        ],
    ],
];