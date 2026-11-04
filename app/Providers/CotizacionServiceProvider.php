<?php

namespace App\Providers;

use App\Contracts\EstrategiaCotizacionMultiple;
use App\Contracts\NotificadorCotizacion;
use App\Services\NotificadorCotizacionWhatsAppCorreo;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class CotizacionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificadorCotizacion::class, NotificadorCotizacionWhatsAppCorreo::class);

        $this->app->bind(EstrategiaCotizacionMultiple::class, function ($app) {
            $modo = config('ordenes_compra.cotizacion_multiple.modo');
            $clase = config("ordenes_compra.cotizacion_multiple.estrategias.{$modo}");

            if (!$clase || !is_subclass_of($clase, EstrategiaCotizacionMultiple::class)) {
                throw new InvalidArgumentException("Modo de cotización múltiple no válido: {$modo}");
            }

            return $app->make($clase);
        });
    }
}