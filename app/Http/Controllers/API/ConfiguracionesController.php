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
                'factor' => 'required|integer',
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
                    'factor' => $request->factor,
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
                'factor' => $request->factor,
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
            $plataforma = $request->plataforma === 'club_bohn' ? 'club bohn' : $request->plataforma;

            $plataformaModel = Plataformas::where('nombre', $plataforma)->first();
            if (!$plataformaModel) {
                return $this->sendError('La plataforma ' . $request->plataforma . ' no existe', 'error', 404);
            }
            $id_plataforma = $plataformaModel->id;

            $variables = DB::table('dc_variables_globales')->where('id_plataforma', $id_plataforma)->first();

            if (!$variables) {
                return $this->sendError('No existen variables globales para la plataforma ' . $request->plataforma, 'error', 404);
            }

            $productosSincronizados = 0;

            $productos = DB::table('dc_catalogo_productos')
                ->where('id_plataforma', $id_plataforma)
                ->whereNotIn('id', function ($query) {
                    $query->select('id_producto')->from('dc_producto_editado');
                })
                ->get();

            foreach ($productos as $producto) {

                $fee_brimagy = $variables->fee_brimagy === 0 ? $producto->fee_brimagy : $variables->fee_brimagy;
                $envio_base = $variables->envio_base === 0 ? $producto->envio_base : $variables->envio_base;
                $costo_caja = $variables->costo_caja === 0 ? $producto->costo_caja : $variables->costo_caja;
                $envio_extra = $variables->envio_extra === 0 ? $producto->envio_extra : $variables->envio_extra;
                $valor_factor = $variables->factor === 0 ? $producto->valor_factor : $variables->factor;

                $costo_proveedor_con_iva = $producto->costo_con_iva;
                $costo_proveedor_sin_iva = $costo_proveedor_con_iva / 1.16;

                $costo_puntos_con_iva = $producto->costo_puntos_con_iva;
                $costo_puntos_sin_iva = $costo_puntos_con_iva / 1.16;

                $porcentaje = (float) $fee_brimagy / 100;
                $valor_con_fee = (float) $costo_puntos_sin_iva * $porcentaje;
                $subtotal = $costo_puntos_sin_iva + $valor_con_fee;
                $total_envio = $envio_base + $costo_caja + $envio_extra;
                $total = $subtotal + $total_envio;
                $redondeo = ($total % 2 !== 0) ? $total + 2 : $total + 1;
                $puntos = $valor_factor == 0 ? round($total) : round($total * $valor_factor);

                DB::table('dc_catalogo_productos')
                    ->where('id', $producto->id)
                    ->update([
                        'fee_brimagy' => $fee_brimagy,
                        'envio_base' => $envio_base,
                        'costo_caja' => $costo_caja,
                        'envio_extra' => $envio_extra,
                        'costo_con_iva' => $costo_proveedor_con_iva,
                        'costo_sin_iva' => $costo_proveedor_sin_iva,
                        'costo_puntos_con_iva' => $costo_puntos_con_iva,
                        'costo_puntos_sin_iva' => $costo_puntos_sin_iva,
                        'subtotal' => $subtotal,
                        'total_envio' => $total_envio,
                        'total' => $total,
                        'puntos' => $redondeo,
                        'valor_factor' => $valor_factor,
                        'factor' => $puntos,
                        'updated_at' => now()->setTimezone('America/Mexico_City'),
                    ]);

                $productosSincronizados++;
            }

            $user = Auth::user();
            BitacoraEventos::create([
                'evento' => 'Sincronización de productos',
                'descripcion' => "El usuario {$user->id} sincronizó {$productosSincronizados} productos en la plataforma {$request->plataforma}.",
                'id_usuario' => $user->id,
            ]);

            DB::commit();

            return $this->sendResponse([
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
                    'vg.factor',
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

    public function getVariablesGlobalesPorPlataforma(Request $request)
    {
        try {

            $plataforma = $request->plataforma === 'club_bohn' ? 'club bohn' : $request->plataforma;
            // es club bohn
            $plataformaModel = Plataformas::where('nombre', $plataforma)->first();
            if (!$plataformaModel) {
                return $this->sendError('La plataforma ' . $request->plataforma . ' no existe', 'error', 404);
            }
            $id_plataforma = $plataformaModel->id;

            $query = DB::table('dc_variables_globales as vg')
                ->select(
                    'vg.id',
                    'vg.fee_brimagy',
                    'vg.envio_base',
                    'vg.costo_caja',
                    'vg.envio_extra',
                    'vg.factor',
                    'p.nombre as nombre_plataforma',
                    'p.descripcion',
                    'vg.created_at as fecha_creacion',
                )
                ->leftJoin('dc_plataformas as p', 'p.id', '=', 'vg.id_plataforma')
                ->where('vg.id_plataforma', $id_plataforma);

            $variablesGlobales = $query->orderBy('vg.id', 'asc')->first();

            return $this->sendResponse($variablesGlobales);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener las variables globales', $th, 500);
        }
    }

    public function getProductosSincronizados(Request $request)
    {
        try {
            $plataforma = $request->plataforma === 'club_bohn' ? 'club bohn' : $request->plataforma;

            $plataformaModel = Plataformas::where('nombre', $plataforma)->first();
            if (!$plataformaModel) {
                return $this->sendError('La plataforma ' . $request->plataforma . ' no existe', 'error', 404);
            }
            $id_plataforma = $plataformaModel->id;

            $variable = DB::table('dc_variables_globales')->where('id_plataforma', $id_plataforma)->first();

            if (!$variable) {
                return $this->sendError('No existen variables globales para la plataforma ' . $request->plataforma, 'error', 404);
            }

            $productosDesincronizados = DB::table('dc_catalogo_productos')
                ->where('id_plataforma', $id_plataforma)
                ->where(function ($query) use ($variable) {
                    $query->where('fee_brimagy', '!=', $variable->fee_brimagy)
                        ->orWhere('envio_base', '!=', $variable->envio_base)
                        ->orWhere('costo_caja', '!=', $variable->costo_caja)
                        ->orWhere('envio_extra', '!=', $variable->envio_extra)
                        ->orWhere('valor_factor', '!=', $variable->factor);
                })
                ->whereNotIn('id', function ($query) {
                    $query->select('id_producto')->from('dc_producto_editado');
                })
                ->count();

            return $this->sendResponse([
                'productos_desincronizados' => $productosDesincronizados,
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos no sincronizados', $th, 500);
        }
    }
}
