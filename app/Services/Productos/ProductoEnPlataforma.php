<?php

namespace App\Services\Productos;

use App\Exceptions\RecursoNoEncontradoException;
use App\Models\CatalogoProductos;
use App\Models\Plataformas;
use App\Services\Plataformas\PlataformaCatalogo;
use App\Services\Plataformas\PlataformaCatalogoResolver;
use App\Services\Soporte\ValidaDatos;

/**
 * Busca el producto local y la plataforma donde está publicado.
 * Lo comparten los casos de uso que operan sobre un producto existente.
 */
trait ProductoEnPlataforma
{
    use ValidaDatos;

    /** @return array{0: CatalogoProductos, 1: PlataformaCatalogo} */
    protected function productoYPlataforma(PlataformaCatalogoResolver $plataformas, array $datos): array
    {
        $this->validar(
            $datos,
            ['id_producto' => 'required|integer|exists:dc_catalogo_productos,id'],
            'Falta el id del producto.'
        );

        $producto = CatalogoProductos::find($datos['id_producto']);
        if (!$producto) {
            throw new RecursoNoEncontradoException('Este producto no existe');
        }

        // La plataforma del producto manda; el parámetro del formulario es respaldo
        $registro = Plataformas::find($producto->id_plataforma);
        $plataforma = $registro
            ? $plataformas->para($registro->nombre)
            : $plataformas->desdeParametro($datos['plataforma'] ?? null);

        if (!$plataforma->buscarProductoRemoto($producto->id_producto_brimagy)) {
            throw new RecursoNoEncontradoException('No se encuentra el producto en brimagy');
        }

        return [$producto, $plataforma];
    }
}
