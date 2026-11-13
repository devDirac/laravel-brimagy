<?php

namespace App\Services\ReporteSemanal;

class FilaReporteSemanal
{
    public function mapear(object $registro, ?int $semana): array
    {
        $gestionada = (int) $registro->cantidad_gestionada;
        $entregada = (int) $registro->cantidad_entregada;

        $continuacion = (bool) ($registro->es_continuacion ?? false);
        $gestionada = $continuacion ? 0 : (int) $registro->cantidad_gestionada;
        $entregada = (int) $registro->cantidad_entregada;
        $faltante = (int) ($registro->faltante_acumulado ?? max((int) $registro->cantidad_gestionada - $entregada, 0));

        return [
            'id_producto_almacen' => (int) $registro->id_producto_almacen,
            'id_canje' => (int) $registro->id_canje,
            'folio_canje' => $registro->folio ?? null,
            'id_orden_compra' => $registro->id_orden_compra !== null ? (int) $registro->id_orden_compra : null,
            'no_orden' => $registro->no_orden,
            'semana' => $semana,
            'id_plataforma' => (int) $registro->id_plataforma,
            'plataforma' => $registro->plataforma,

            'id_proveedor' => $registro->id_proveedor !== null ? (int) $registro->id_proveedor : null,
            'nombre_proveedor' => $registro->nombre_proveedor,
            'id_producto' => (int) $registro->id_producto,
            'nombre_producto' => $registro->nombre_producto,
            'sku' => $registro->sku,
            'marca' => $registro->marca,
            'precio' => $this->decimal($registro->precio),

            'cantidad_gestionada' => $gestionada,
            'entrega' => $entregada,
            'faltante' => $faltante,
            'es_continuacion' => $continuacion,
            'es_ultimo' => (bool) ($registro->es_ultimo ?? true),

            'tipo_envio' => $registro->tipo_envio !== null ? (int) $registro->tipo_envio : null,
            'notas' => TipoEnvio::notaDe($registro->tipo_envio),

            'fecha_factura' => $registro->fecha_factura,
            'folio_factura' => $registro->folio_factura,
            'precio_unitario_compra' => $this->decimal($registro->precio_unitario_compra),
            'total' => $this->decimal($registro->total_compra) ?? 0.0,

            'estatus' => $registro->estatus,
            'fecha_referencia' => $registro->fecha_referencia,
        ];
    }

    private function decimal($valor): ?float
    {
        return $valor === null ? null : round((float) $valor, 2);
    }
}
