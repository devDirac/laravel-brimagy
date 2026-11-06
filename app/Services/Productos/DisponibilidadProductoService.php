<?php

namespace App\Services\Productos;

use App\Models\CatalogoProductos;
use App\Services\Plataformas\PlataformaCatalogoResolver;
use App\Services\Soporte\Transacciones;

/**
 * Marca un producto como disponible o no disponible, en local y en la plataforma.
 * La disponibilidad se maneja con el stock: 0 = no disponible.
 */
class DisponibilidadProductoService
{
    use ProductoEnPlataforma;

    const STOCK_DISPONIBLE = 1000000;
    const STOCK_NO_DISPONIBLE = 0;

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

    public function marcar(array $datos, bool $disponible, int $idUsuario): CatalogoProductos
    {
        [$producto, $plataforma] = $this->productoYPlataforma($this->plataformas, $datos);
        $stock = $disponible ? self::STOCK_DISPONIBLE : self::STOCK_NO_DISPONIBLE;

        return $this->transacciones->en($plataforma->conexiones(), function () use ($producto, $plataforma, $stock, $disponible, $idUsuario) {
            $plataforma->actualizarProductoRemoto($producto->id_producto_brimagy, ['stock' => $stock]);
            $producto->update(['stock' => $stock]);

            $estado = $disponible ? 'disponible' : 'no disponible';
            $this->bitacora->registrar(
                "Producto marcado como {$estado}",
                BitacoraProductos::TABLA,
                $producto->id,
                // Antes marcarDisponible también decía "como no disponible"
                "El usuario con id: {$idUsuario} marcó el producto {$producto->nombre_producto} como {$estado}",
                $idUsuario
            );

            return $producto;
        });
    }
}
