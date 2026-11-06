<?php

namespace App\Services\Productos;

use App\Services\Plataformas\PlataformaCatalogoResolver;
use App\Services\Soporte\Transacciones;

/**
 * Elimina un producto: en la plataforma se desactiva (status INACTIVE) para no romper
 * los canjes que ya lo usan, y en el catálogo local se borra.
 */
class EliminarProductoService
{
    use ProductoEnPlataforma;

    private $plataformas;
    private $transacciones;
    private $bitacora;

    public function __construct(
        PlataformaCatalogoResolver $plataformas,
        Transacciones $transacciones,
        BitacoraProductos $bitacora
    ) {
        $this->plataformas = $plataformas;
        $this->transacciones = $transacciones;
        $this->bitacora = $bitacora;
    }

    public function eliminar(array $datos, int $idUsuario): void
    {
        [$producto, $plataforma] = $this->productoYPlataforma($this->plataformas, $datos);

        $this->transacciones->en($plataforma->conexiones(), function () use ($producto, $plataforma, $idUsuario) {
            $plataforma->actualizarProductoRemoto($producto->id_producto_brimagy, ['status' => 'INACTIVE']);

            // Se registra antes de borrar para conservar el id y el nombre
            $this->bitacora->registrar(
                'Eliminación de producto',
                BitacoraProductos::TABLA,
                $producto->id,
                "El usuario con id: {$idUsuario} eliminó el producto {$producto->nombre_producto} (id: {$producto->id})",
                $idUsuario
            );

            $producto->delete();
        });
    }
}
