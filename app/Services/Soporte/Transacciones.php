<?php

namespace App\Services\Soporte;

use Illuminate\Support\Facades\DB;

/**
 * Transacción en varias conexiones a la vez (local, Brimagy, Club…).
 * Antes solo se abría en la conexión por defecto, así que un error a la mitad
 * dejaba guardado lo que ya se había escrito en Brimagy o Club.
 *
 * No es un commit atómico entre bases distintas: si falla el commit de una conexión
 * después de que otra ya hizo commit, esa otra no se revierte. Cubre el caso común
 * (una excepción mientras se trabaja), que es donde antes quedaban datos a medias.
 */
class Transacciones
{
    /**
     * @param array    $conexiones Nombres de conexión; la conexión por defecto siempre se incluye
     * @param callable $trabajo
     * @return mixed Lo que regrese $trabajo
     */
    public function en(array $conexiones, callable $trabajo)
    {
        $porDefecto = config('database.default');
        $conexiones = array_values(array_unique(array_map(function ($conexion) use ($porDefecto) {
            return $conexion ?: $porDefecto;
        }, array_merge([$porDefecto], $conexiones))));

        $abiertas = [];
        try {
            foreach ($conexiones as $conexion) {
                DB::connection($conexion)->beginTransaction();
                $abiertas[] = $conexion;
            }

            $resultado = $trabajo();

            foreach ($abiertas as $conexion) {
                DB::connection($conexion)->commit();
            }

            return $resultado;
        } catch (\Throwable $th) {
            foreach (array_reverse($abiertas) as $conexion) {
                try {
                    DB::connection($conexion)->rollBack();
                } catch (\Throwable $errorRollback) {
                    report($errorRollback);
                }
            }

            throw $th;
        }
    }
}
