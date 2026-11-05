<?php

namespace App\Services;

use App\Contracts\EstrategiaCotizacionMultiple;
use App\Contracts\NotificadorCotizacion;
use Illuminate\Support\Facades\DB;

class EnviarCotizacionDirecta implements EstrategiaCotizacionMultiple
{
    public function __construct(
        private DuplicadorValidacionCanje $duplicador,
        private OrdenCompraService $ordenes,
        private NotificadorCotizacion $notificador,
    ) {
    }

    public function ejecutar(array $asignaciones, int $idUsuario): ResultadoCotizacionMultiple
    {
        return DB::transaction(function () use ($asignaciones, $idUsuario) {
            $validaciones = $this->duplicador->duplicar($asignaciones);

            $ordenes = $validaciones
                ->groupBy('id_proveedor')
                ->map(function ($grupo, $idProveedor) use ($idUsuario) {
                    $orden = $this->ordenes->crearOrden(
                        $grupo->map(fn($v) => [
                            'id_validacion' => $v->id,
                            'estatus_proveedor' => 0,
                            'estatus_almacen' => 0,
                        ])->values()->all(),
                        (int) $idProveedor,
                        $idUsuario,
                        'proveedor'
                    );

                    DB::afterCommit(fn() => $this->notificador->cotizacionEnviada(
                        $orden,
                        array_values(json_decode($orden->productos_canje, true) ?: [])
                    ));

                    return $orden;
                });

            return new ResultadoCotizacionMultiple(
                'directo',
                $validaciones->filter(fn($v) => $v->wasRecentlyCreated)->count(),
                $ordenes->count(),
                "Se enviaron {$ordenes->count()} cotización(es)."
            );
        });
    }
}