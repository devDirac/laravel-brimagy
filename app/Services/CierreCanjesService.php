<?php

namespace App\Services;

use App\Models\OrdenCompra;
use App\Models\Plataformas;
use App\Models\ValidacionCanje;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CierreCanjesService
{
    public const ESTATUS_ACEPTADO = 1;
    public const ESTATUS_RECHAZADO = 2;
    public const ESTATUS_DESCARTADO = 3;

    public function __construct(private FolioCanjeResolver $folios)
    {
    }

    /** Folio del canje en la BD de su plataforma; si no se encuentra, el id del canje */
    private function folioCanje(int $idCanje, ?int $idPlataforma): string
    {
        $canje = $this->folios->enriquecer(
            collect([(object) ['id_canje' => $idCanje, 'id_plataforma' => $idPlataforma]]),
            Plataformas::pluck('nombre', 'id')
        )->first();

        return $canje->folio ?? "#{$idCanje}";
    }

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

            $folio = $this->folioCanje(
                $g['id_canje'],
                ValidacionCanje::whereKey($g['id_validacion'])->value('id_plataforma')
            );

            if ($conflicto) {
                throw new \DomainException(
                    "El producto del canje {$folio} ya está en la orden de compra {$conflicto->no_orden}."
                );
            }
        }
    }

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

    /**
     * Retira el registro original del proveedor que lo tenía, porque el producto
     * se mandó a cotizar con otros proveedores:
     * - Si está en pendientes (sin oc), deja de aparecer en ellos.
     * - Si está en una cotización abierta, su línea queda descartada en esa orden.
     * No se permite si el registro ya está en una compra en curso.
     */
    public function retirarPorReasignacion(ValidacionCanje $origen): void
    {
        if (!empty($origen->no_orden)) {
            $orden = OrdenCompra::where('no_orden', $origen->no_orden)->lockForUpdate()->first();

            if ($orden && in_array($orden->estatus, self::ORDENES_EN_CURSO, true)) {
                $folio = $this->folioCanje((int) $origen->id_canje, $origen->id_plataforma);

                throw new \DomainException(
                    "El producto del canje {$folio} ya está en la orden de compra {$orden->no_orden}; no se puede cotizar con otro proveedor."
                );
            }

            if ($orden) {
                $this->descartarLineas(
                    $orden,
                    fn($l) => (int) ($l['id_validacion'] ?? 0) === (int) $origen->id,
                    'Reasignado a otro proveedor'
                );
            }
        }

        $origen->fecha_reasignacion = now();
        $origen->save();
    }

    private function descartarEnOtrasOrdenes(OrdenCompra $orden, array $g): void
    {
        $this->otrasOrdenesDelGrupo($orden, $g, true)->each(function (OrdenCompra $oc) use ($orden, $g) {
            if (in_array($oc->estatus, self::ORDENES_EN_CURSO, true)) {
                Log::warning("Cierre {$orden->no_orden}: la orden {$oc->no_orden} ya está en curso con el canje {$g['id_canje']}");
                return;
            }

            $this->descartarLineas(
                $oc,
                fn($l) => (int) ($l['id_canje'] ?? 0) === $g['id_canje']
                    && (int) ($l['id_producto'] ?? 0) === $g['id_producto'],
                "Asignado en la orden {$orden->no_orden}"
            );
        });
    }

    private function descartarLineas(OrdenCompra $oc, callable $coincide, string $motivo): void
    {
        $lineas = json_decode($oc->productos_canje, true) ?: [];
        $cambio = false;

        foreach ($lineas as $llave => $l) {
            if ($coincide($l) && (int) ($l['estatus_proveedor'] ?? 0) !== self::ESTATUS_DESCARTADO) {
                $lineas[$llave]['estatus_proveedor'] = self::ESTATUS_DESCARTADO;
                $lineas[$llave]['motivo_descarte'] = $motivo;
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