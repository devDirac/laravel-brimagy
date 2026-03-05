<?php

namespace App\Http\Controllers\API;

use App\Models\Evidencias;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class EvidenciasController extends BaseController
{
    public function subirEvidencias(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_producto_almacen' => 'required',
            'evidencias' => 'required|array',
            'evidencias.*' => 'required|file|mimes:jpg,jpeg,png|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $archivosSubidos = [];

            // Subir cada archivo
            foreach ($request->file('evidencias') as $archivo) {
                $nombreOriginal = $archivo->getClientOriginalName();
                $extension = $archivo->getClientOriginalExtension();
                $nombreUnico = time() . '_' . uniqid() . '.' . $extension;

                $ruta = $archivo->storeAs(
                    'evidencias/' . $request->id_producto_almacen,
                    $nombreUnico,
                    'private'
                );

                $archivosSubidos[] = [
                    'nombre_original' => $nombreOriginal,
                    'nombre_archivo' => $nombreUnico,
                    'ruta' => $ruta,
                    'url' => asset($ruta),
                    'tipo' => $extension,
                    'tamano' => $archivo->getSize(),
                ];
            }

            // Buscar si ya existe un registro de evidencias para este almacen
            $evidencia = Evidencias::where('id_almacen_producto', $request->id_producto_almacen)->first();

            if ($evidencia) {
                // Si existe, agregar las nuevas evidencias al array existente
                $evidenciasActuales = json_decode($evidencia->evidencias, true) ?? [];
                $evidenciasActualizadas = array_merge($evidenciasActuales, $archivosSubidos);

                $evidencia->update([
                    'evidencias' => json_encode($evidenciasActualizadas)
                ]);
            } else {
                $evidencia = Evidencias::create([
                    'id_almacen_producto' => $request->id_producto_almacen,
                    'evidencias' => json_encode($archivosSubidos),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Evidencias subidas correctamente',
                'evidencias' => json_decode($evidencia->evidencias),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir evidencias',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
