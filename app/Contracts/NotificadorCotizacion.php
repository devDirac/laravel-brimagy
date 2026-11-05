<?php

namespace App\Contracts;

use App\Models\OrdenCompra;

interface NotificadorCotizacion
{
    public function cotizacionEnviada(OrdenCompra $orden, array $productos): void;
}