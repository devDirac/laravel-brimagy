<?php

namespace App\Services;

use App\Models\OrdenCompra;
use App\Models\ValidacionCanje;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CierreCanjesService
{
    public const ESTATUS_ACEPTADO = 1;
    public const ESTATUS_RECHAZADO = 2;
    public const ESTATUS_DESCARTADO = 3;

    /** Órdenes que ya son una compra en curso: no se tocan ni se duplican */
    private const ORDENES_EN_CURSO = [
        'orden_compra_enviada_a_proveedor',
        'orden_validada_por_proveedor',
        'xml_validado_correctamente_proveedor',
        'factura_subida_correctamente_proveedor',
    ];

    public function grupoEjecutado(int $idCanje, int $idProducto): bool
    {
        return ValidacionCanje::where('id_canje', $idCanje)
            ->where('id_producto', $idProducto)
            ->whereNotNull('orden_ejecutada')
            ->exists();
    }

    /**
     * Candado antes de enviar una orden de compra: ningún producto aceptado
     * puede estar ya en otra compra en curso ni estar ejecutado.
     */
    public function asegurarSinCompraDuplicada(OrdenCompra $orden): void
    {
        foreach ($this->gruposAceptados($orden) as $g) {
            if ($this->grupoEjecutado($g['id_canje'], $g['id_producto'])) {
                throw new \DomainException("El producto del canje {$g['id_canje']} ya fue ejecutado en otra orden.");
            }

            $conflicto = $this->otrasOrdenesDelGrupo($orden, $g)
                ->whereIn('estatus', self::ORDENES_EN_CURSO)
                ->first(fn($oc) => $this->gruposAceptados($oc)->contains(
                    fn($x) => $x['id_canje'] === $g['id_canje'] && $x['id_producto'] === $g['id_producto']
                ));

            if ($conflicto) {
                throw new \DomainException(
                    "El producto del canje {$g['id_canje']} ya está en la orden de compra {$conflicto->no_orden}."
                );
            }
        }
    }

    /** Marca como ejecutados los grupos aceptados en la orden y descarta el resto. Idempotente. */
    public function cerrarPorOrden(OrdenCompra $orden): void
    {
        foreach ($this->gruposAceptados($orden) as $g) {
            ValidacionCanje::where('id_canje', $g['id_canje'])
                ->where('id_producto', $g['id_producto'])
                ->whereNull('orden_ejecutada')
                ->update([
                    'orden_ejecutada' => $orden->no_orden,
                    'fecha_ejecucion' => now(),
                ]);

            $this->descartarEnOtrasOrdenes($orden, $g);
        }
    }

    private function descartarEnOtrasOrdenes(OrdenCompra $orden, array $g): void
    {
        $this->otrasOrdenesDelGrupo($orden, $g, true)->each(function (OrdenCompra $oc) use ($orden, $g) {
            if (in_array($oc->estatus, self::ORDENES_EN_CURSO, true)) {
                Log::warning("Cierre {$orden->no_orden}: la orden {$oc->no_orden} ya está en curso con el canje {$g['id_canje']}");
                return;
            }

            $lineas = json_decode($oc->productos_canje, true) ?: [];
            $cambio = false;

            foreach ($lineas as $llave => $l) {
                $mismoGrupo = (int) ($l['id_canje'] ?? 0) === $g['id_canje']
                    && (int) ($l['id_producto'] ?? 0) === $g['id_producto'];

                if ($mismoGrupo && (int) ($l['estatus_proveedor'] ?? 0) !== self::ESTATUS_DESCARTADO) {
                    $lineas[$llave]['estatus_proveedor'] = self::ESTATUS_DESCARTADO;
                    $lineas[$llave]['motivo_descarte'] = "Asignado en la orden {$orden->no_orden}";
                    $cambio = true;
                }
            }

            if (!$cambio) {
                return;
            }

            $sinLineasVivas = collect($lineas)->every(fn($l) => in_array(
                (int) ($l['estatus_proveedor'] ?? 0),
                [self::ESTATUS_RECHAZADO, self::ESTATUS_DESCARTADO],
                true
            ));

            $oc->productos_canje = json_encode($lineas, JSON_FORCE_OBJECT);
            if ($sinLineasVivas) {
                $oc->estatus = 'cotizacion_descartada';
            }
            $oc->save();
        });
    }

    private function otrasOrdenesDelGrupo(OrdenCompra $orden, array $g, bool $bloquear = false): Collection
    {
        $nos = ValidacionCanje::where('id_canje', $g['id_canje'])
            ->where('id_producto', $g['id_producto'])
            ->whereNotNull('no_orden')
            ->where('no_orden', '!=', $orden->no_orden)
            ->pluck('no_orden')
            ->unique();

        if ($nos->isEmpty()) {
            return collect();
        }

        $q = OrdenCompra::whereIn('no_orden', $nos);
        return ($bloquear ? $q->lockForUpdate() : $q)->get();
    }

    private function gruposAceptados(OrdenCompra $orden): Collection
    {
        return collect(json_decode($orden->productos_canje, true) ?: [])
            ->filter(fn($l) => (int) ($l['estatus_proveedor'] ?? 0) === self::ESTATUS_ACEPTADO
                && !empty($l['id_canje']) && !empty($l['id_producto']))
            ->map(fn($l) => [
                'id_canje' => (int) $l['id_canje'],
                'id_producto' => (int) $l['id_producto'],
                'id_validacion' => (int) ($l['id_validacion'] ?? 0),
            ])
            ->values();
    }
}