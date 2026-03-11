<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\BitacoraEventos;
use App\Models\Plataformas;
use App\Models\VariablesGlobales;

class ConfiguracionesController extends BaseController
{
    public function crearVariablesGlobales(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'fee_brimagy' => 'required|integer',
                'envio_base' => 'required|integer',
                'costo_caja' => 'required|integer',
                'envio_extra' => 'required|integer',
                'id_plataforma' => 'required|integer',
                'tipo' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            if ($request->tipo === "actualizar") {
                $VariablesExistentes = VariablesGlobales::where('id', $request->id)->first();
            } else {
                $VariablesExistentes = VariablesGlobales::where('id_plataforma', $request->id_plataforma)->first();
            }


            if ($VariablesExistentes) {
                $VariablesExistentes->update([
                    'fee_brimagy' => $request->fee_brimagy,
                    'envio_base' => $request->envio_base,
                    'costo_caja' => $request->costo_caja,
                    'envio_extra' => $request->envio_extra,
                    'id_plataforma' => $request->id_plataforma,
                    'updated_at' => now()->setTimezone('America/Mexico_City'),
                ]);

                $user = Auth::user();
                $log['evento'] = 'Actualización de variables globales';
                $log['descripcion'] = "El usuario con id: {$user->id} actualizó las variables globales";
                $log['id_usuario'] = $user->id;
                BitacoraEventos::create($log);

                DB::commit();

                return $this->sendResponse($VariablesExistentes, 'Variables globales actualizadas exitosamente.');
            }

            $variable = VariablesGlobales::create([
                'fee_brimagy' => $request->fee_brimagy,
                'envio_base' => $request->envio_base,
                'costo_caja' => $request->costo_caja,
                'envio_extra' => $request->envio_extra,
                'id_plataforma' => $request->id_plataforma,
            ]);

            $user = Auth::user();
            $log['evento'] = 'Creación de variables globales';
            $log['descripcion'] = "El usuario con id: {$user->id} añadió las variables globales";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($variable, 'Variables globales registradas exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar las variables globales', $th->getMessage(), 500);
        }
    }

    public function sincronizarVariablesEnProductos(Request $request)
    {
        DB::beginTransaction();

        try {
            $variables = DB::table('dc_variables_globales')->get();

            $plataformasSincronizadas = 0;
            $productosSincronizados = 0;

            foreach ($variables as $variable) {
                // Obtener productos desincronizados
                $productosDesincronizados = DB::table('dc_catalogo_productos')
                    ->where('id_plataforma', $variable->id_plataforma)
                    ->where(function ($query) use ($variable) {
                        $query->where('fee_brimagy', '!=', $variable->fee_brimagy)
                            ->orWhere('envio_base', '!=', $variable->envio_base)
                            ->orWhere('costo_caja', '!=', $variable->costo_caja)
                            ->orWhere('envio_extra', '!=', $variable->envio_extra);
                    })
                    ->get();

                if ($productosDesincronizados->isEmpty()) {
                    continue;
                }

                foreach ($productosDesincronizados as $producto) {
                    $porcentaje = (float) $variable->fee_brimagy / 100;
                    $valor_con_fee = round((float) $producto->costo_puntos_sin_iva * $porcentaje, 2);
                    $subtotal = $producto->costo_puntos_sin_iva + $valor_con_fee;
                    $total_envio = $variable->envio_base + $variable->costo_caja + $variable->envio_extra;
                    $total = $subtotal + $total_envio;
                    $puntos = $total + 1;
                    $factor = $puntos * 15;

                    DB::table('dc_catalogo_productos')
                        ->where('id', $producto->id)
                        ->update([
                            'fee_brimagy' => $variable->fee_brimagy,
                            'envio_base' => $variable->envio_base,
                            'costo_caja' => $variable->costo_caja,
                            'envio_extra' => $variable->envio_extra,
                            'subtotal' => $subtotal,
                            'total_envio' => $total_envio,
                            'total' => $total,
                            'puntos' => $puntos,
                            'factor' => $factor,
                            'updated_at' => now()->setTimezone('America/Mexico_City'),
                        ]);

                    $productosSincronizados++;
                }

                $plataformasSincronizadas++;
            }

            $user = Auth::user();
            BitacoraEventos::create([
                'evento' => 'Sincronización de productos',
                'descripcion' => "El usuario {$user->id} sincronizó {$productosSincronizados} productos en {$plataformasSincronizadas} plataformas.",
                'id_usuario' => $user->id,
            ]);

            DB::commit();

            return $this->sendResponse([
                'plataformas_sincronizadas' => $plataformasSincronizadas,
                'productos_sincronizados' => $productosSincronizados,
            ], 'Sincronización completada exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al sincronizar los productos', $th->getMessage(), 500);
        }
    }

    public function crearPlataforma(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string',
                'descripcion' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $plataformaExiste = Plataformas::where('id', $request->id_plataforma)->first();

            if ($plataformaExiste) {
                $plataformaExiste->update([
                    'nombre' => $request->nombre,
                    'descripcion' => $request->descripcion,
                    'updated_at' => now()->setTimezone('America/Mexico_City'),
                ]);

                $user = Auth::user();
                $log['evento'] = 'Actualización de plataforma';
                $log['descripcion'] = "El usuario con id: {$user->id} actualizó la plataforma {$request->id_plataforma}";
                $log['id_usuario'] = $user->id;
                BitacoraEventos::create($log);

                DB::commit();

                return $this->sendResponse($plataformaExiste, 'Plataforma actualizada correctamente.');
            }

            $plataforma = Plataformas::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
            ]);

            $user = Auth::user();
            $log['evento'] = 'Creación de plataforma';
            $log['descripcion'] = "El usuario con id: {$user->id} añadió la plataforma {$request->nombre}";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($plataforma, 'Plataforma registrada exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar la plataforma', $th->getMessage(), 500);
        }
    }

    public function getPlataformas(Request $request)
    {
        try {
            $plataformas = Plataformas::get();

            return $this->sendResponse($plataformas);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener las plataformas', $th, 500);
        }
    }

    public function getVariablesGlobales(Request $request)
    {
        try {

            $query = DB::table('dc_variables_globales as vg')
                ->select(
                    'vg.id',
                    'vg.fee_brimagy',
                    'vg.envio_base',
                    'vg.costo_caja',
                    'vg.envio_extra',
                    'p.nombre as nombre_plataforma',
                    'p.descripcion',
                    'vg.created_at as fecha_creacion',
                )
                ->leftJoin('dc_plataformas as p', 'p.id', '=', 'vg.id_plataforma');

            $variablesGlobales = $query->orderBy('vg.id', 'asc')->get();

            return $this->sendResponse($variablesGlobales);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener las variables globales', $th, 500);
        }
    }

    public function getProductosSincronizados(Request $request)
    {
        try {
            $variables = DB::table('dc_variables_globales')->get();

            $plataformasDesincronizadas = 0;
            $productosDesincronizados = 0;

            foreach ($variables as $variable) {
                $cantidad = DB::table('dc_catalogo_productos')
                    ->where('id_plataforma', $variable->id_plataforma)
                    ->where(function ($query) use ($variable) {
                        $query->where('fee_brimagy', '!=', $variable->fee_brimagy)
                            ->orWhere('envio_base', '!=', $variable->envio_base)
                            ->orWhere('costo_caja', '!=', $variable->costo_caja)
                            ->orWhere('envio_extra', '!=', $variable->envio_extra);
                    })
                    ->count();

                if ($cantidad > 0) {
                    $plataformasDesincronizadas++;
                    $productosDesincronizados += $cantidad;
                }
            }

            return $this->sendResponse([
                'plataformas_desincronizadas' => $plataformasDesincronizadas,
                'productos_desincronizados' => $productosDesincronizados,
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos no sincronizados', $th, 500);
        }
    }
}
