<?php

namespace App\Services\Productos;

use App\Models\BitacoraEventos;

/** Registros de productos en la bitácora de eventos. */
class BitacoraProductos
{
    const TABLA = 'dc_catalogo_productos';

    /** Registro libre en la bitácora. */
    public function registrar(string $evento, ?string $tabla, $idReferencia, string $descripcion, int $idUsuario): void
    {
        BitacoraEventos::create([
            'evento' => $evento,
            'tabla' => $tabla,
            'id_referencia' => $idReferencia,
            'descripcion' => $descripcion,
            'id_usuario' => $idUsuario,
        ]);
    }

    /** "campo: anterior → nuevo | ..." de los campos que cambiaron, o $sinCambios. */
    public function describirCambios(array $anteriores, array $nuevos, string $sinCambios = 'Sin cambios'): string
    {
        $cambios = [];
        foreach ($nuevos as $campo => $nuevo) {
            $anterior = $anteriores[$campo] ?? null;
            if ((string) $anterior !== (string) $nuevo) {
                $cambios[] = "{$campo}: {$anterior} → {$nuevo}";
            }
        }

        return !empty($cambios) ? implode(' | ', $cambios) : $sinCambios;
    }

    public function registrarCreacion(int $idUsuario, int $idProducto): void
    {
        BitacoraEventos::create([
            'evento' => 'Creación de producto',
            'tabla' => self::TABLA,
            'id_referencia' => $idProducto,
            'descripcion' => "El usuario con id: {$idUsuario} añadio el producto con id: {$idProducto} al catalogo",
            'id_usuario' => $idUsuario,
        ]);
    }

    /**
     * Registra qué campos del cálculo cambiaron (comparados a 2 decimales).
     * $textoSinCambios se usa cuando ningún campo numérico cambió.
     */
    public function registrarEdicion(
        int $idUsuario,
        int $idProducto,
        array $anteriores,
        array $nuevos,
        string $textoSinCambios
    ): void {
        $cambios = [];
        foreach (CalculadoraPuntos::camposAuditables() as $campo) {
            $anterior = $anteriores[$campo] ?? null;
            $nuevo = $nuevos[$campo] ?? null;

            if ($nuevo !== null && round((float) $anterior, 2) !== round((float) $nuevo, 2)) {
                $cambios[] = "{$campo}: {$anterior} → {$nuevo}";
            }
        }

        $descripcion = !empty($cambios) ? implode(' | ', $cambios) : $textoSinCambios;

        BitacoraEventos::create([
            'evento' => 'Edición de producto',
            'tabla' => self::TABLA,
            // Antes se usaba $request->id_producto, que no viene al crear/actualizar desde Excel
            'id_referencia' => $idProducto,
            'descripcion' => "Se editó la siguiente información del producto: {$descripcion}",
            'id_usuario' => $idUsuario,
        ]);
    }
}
