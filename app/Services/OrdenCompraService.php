<?php

namespace App\Services;

use App\Models\OrdenCompra;
use App\Models\RecepcionAlmacen;
use App\Models\ValidacionCanje;
use Illuminate\Support\Facades\DB;

class OrdenCompraService
{
    private const IVA = 0.16;
    private const MAX_INTENTOS_NO_ORDEN = 100;

    /**
     * Crea UNA orden con todas las validaciones pendientes indicadas,
     * sin importar su plataforma.
     *
     * @param array $lineas [['id_validacion' => int, 'estatus_proveedor' => int, 'estatus_almacen' => int], ...]
     */
    public function crearOrden(
        array $lineas,
        ?int $idProveedor,
        int $idUsuario,
        string $tipoEnvio,
        ?string $observaciones = null
    ): OrdenCompra {
        $lineas = collect($lineas)->keyBy('id_validacion');

        $validaciones = ValidacionCanje::whereIn('id', $lineas->keys())
            ->where(fn($q) => $q->whereNull('no_orden')->orWhere('no_orden', ''))
            ->get();

        if ($validaciones->isEmpty()) {
            throw new \DomainException('Los productos ya no están pendientes.');
        }

        $catalogo = DB::table('dc_catalogo_productos')
            ->whereIn('id', $validaciones->pluck('id_producto'))
            ->get(['id', 'nombre_producto', 'sku', 'costo_sin_iva', 'id_plataforma'])
            ->keyBy('id');

        $productos = $validaciones
            ->filter(fn($v) => $catalogo->has($v->id_producto))
            ->mapWithKeys(fn($v) => [
                $v->id => $this->armarLinea(
                    $catalogo[$v->id_producto],
                    $v,
                    (int) ($lineas[$v->id]['estatus_proveedor'] ?? 0),
                    (int) ($lineas[$v->id]['estatus_almacen'] ?? 0)
                ),
            ])
            ->all();

        if (empty($productos)) {
            throw new \DomainException('Los productos seleccionados no existen en el catálogo.');
        }

        $numeroOrden = $this->generarNumeroOrdenUnico();

        $orden = OrdenCompra::create([
            'id_usuario'      => $idUsuario,
            'id_proveedor'    => $idProveedor,
            'no_orden'        => $numeroOrden,
            'productos_canje' => json_encode($productos, JSON_FORCE_OBJECT),
            'observaciones'   => $observaciones,
            'id_plataforma'   => null,
            'estatus'         => $tipoEnvio === 'directo'
                ? 'orden_compra_enviada_a_proveedor'
                : 'cotizacion_enviada_a_proveedor',
        ]);

        ValidacionCanje::whereIn('id', array_keys($productos))
            ->update(['no_orden' => $numeroOrden]);

        if ($tipoEnvio === 'directo') {
            $this->registrarEnAlmacen($orden, $productos);
        }

        return $orden;
    }

    public function armarLinea(
        object $producto,
        ValidacionCanje $validacion,
        int $estatusProveedor = 0,
        int $estatusAlmacen = 0
    ): array {
        $precio   = (float) ($producto->costo_sin_iva ?? 0);
        $cantidad = (int) ($validacion->cantidad_producto ?? 0);
        $subtotal = $precio * $cantidad;
        $iva      = $subtotal * self::IVA;

        return [
            'id_validacion'     => $validacion->id,
            'id_canje'          => $validacion->id_canje,
            'id_producto'       => $validacion->id_producto,
            'id_plataforma'     => $producto->id_plataforma,
            'nombre_producto'   => $producto->nombre_producto,
            'sku'               => $producto->sku,
            'cantidad_producto' => $cantidad,
            'precio_unitario'   => $precio,
            'subtotal'          => $subtotal,
            'iva'               => $iva,
            'importe_total'     => $subtotal + $iva,
            'estatus_proveedor' => $estatusProveedor,
            'estatus_almacen'   => $estatusAlmacen,
            'tipo_compra'       => '',
        ];
    }

    private function registrarEnAlmacen(OrdenCompra $orden, array $productos): void
    {
        foreach ($productos as $p) {
            if ((int) $p['estatus_proveedor'] !== 1) {
                continue;
            }

            RecepcionAlmacen::create([
                'id_canje'          => $p['id_canje'],
                'id_usuario'        => $orden->id_usuario,
                'id_producto'       => $p['id_producto'],
                'id_orden_compra'   => $orden->id,
                'cantidad_producto' => $p['cantidad_producto'],
                'cantidad_almacen'  => 0,
                'id_proveedor'      => null,
                'precio_compra'     => $p['precio_unitario'],
                'fecha'             => now(),
                'comentarios'       => '',
                'guia'              => '',
                'cantidad'          => $p['cantidad_producto'],
                'sku'               => $p['sku'],
                'nombre_producto'   => $p['nombre_producto'],
            ]);
        }
    }

    private function generarNumeroOrdenUnico(): string
    {
        for ($intento = 0; $intento < self::MAX_INTENTOS_NO_ORDEN; $intento++) {
            $numeroOrden = 'OCB-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            if (!OrdenCompra::where('no_orden', $numeroOrden)->exists()) {
                return $numeroOrden;
            }
        }

        throw new \RuntimeException(
            'No se pudo generar un número de orden único después de ' . self::MAX_INTENTOS_NO_ORDEN . ' intentos'
        );
    }
}
