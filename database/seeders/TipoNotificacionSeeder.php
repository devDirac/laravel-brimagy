<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TipoNotificacionSeeder extends Seeder
{
    /**
     * Catálogo de tipos de notificación.
     * El id se usa en backend y frontend
     *
     * 1 → OrdenCompraController::enviarOCAprobacion
     * 2 → OrdenCompraController::validarOrdenCompraFinal
     * 3 → EncuestasController::addRespuestasEncuestaUsuario
     * 4 → NotificacionesController::setNotificacion (avisos manuales desde el frontend)
     */
    private const TIPOS = [
        1 => [
            'tipo_notificacion' => 'Órdenes de compra',
            'descripcion' => 'Cotización pendiente de revisión',
        ],
        2 => [
            'tipo_notificacion' => 'Órdenes de compra',
            'descripcion' => 'Orden de compra validada',
        ],
        3 => [
            'tipo_notificacion' => 'Encuestas',
            'descripcion' => 'Encuesta de entrega contestada',
        ],
        4 => [
            'tipo_notificacion' => 'Avisos generales',
            'descripcion' => 'Aviso general',
        ],
    ];

    public function run(): void
    {
        $tabla = 'dc_tipo_notificacion';
        $conTimestamps = Schema::hasColumn($tabla, 'created_at');

        foreach (self::TIPOS as $id => $valores) {
            if ($conTimestamps) {
                $valores['updated_at'] = now();

                if (!DB::table($tabla)->where('id', $id)->exists()) {
                    $valores['created_at'] = now();
                }
            }

            DB::table($tabla)->updateOrInsert(['id' => $id], $valores);
        }
    }
}