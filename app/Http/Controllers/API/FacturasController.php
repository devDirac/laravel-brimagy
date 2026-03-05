<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Facturas;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class FacturasController extends BaseController
{
    public function addFechaPagoFactura(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_orden_compra' => 'required|integer',
                'fecha_pago' => 'required|date'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            // Actualizar TODAS las facturas con esa orden de compra
            $updated = Facturas::where('id_orden_compra', $request->id_orden_compra)
                ->update(['fecha_pago' => $request->fecha_pago]);

            if ($updated == 0) {
                DB::rollBack();
                return $this->sendError('No se encontraron facturas para actualizar.');
            }

            DB::commit();

            return $this->sendResponse("Fecha de pago añadida correctamente.");
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al añadir la fecha de pago', $th->getMessage(), 500);
        }
    }
}
