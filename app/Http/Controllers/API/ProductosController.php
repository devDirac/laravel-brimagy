<?php

namespace App\Http\Controllers\API;

use App\Models\Colores;
use App\Models\ColoresBrimagy;
use App\Models\FotoMontos;
use App\Models\FotosOfertasClub;
use App\Models\Montos;
use App\Models\MontosBrimagy;
use App\Models\MontosClub;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\BitacoraEventos;
use App\Models\CatalogoCategoria;
use App\Models\CatalogoProductos;
use App\Models\CatalogoProveedores;
use App\Models\CategoriaClub;
use App\Models\ColoresClub;
use App\Models\FotosOfertas;
use App\Models\FotosOfertasBrimagy;
use App\Models\FotosProducto;
use App\Models\FotosProductoBrimagy;
use App\Models\FotosProductoClub;
use App\Models\Plataformas;
use App\Models\ProductoBrimagy;
use App\Models\ProductoClub;
use App\Models\ProductoEditado;
use App\Models\SubCategoria;
use App\Models\SubCategoriaClub;
use App\Models\Tallas;
use App\Models\TallasBrimagy;
use App\Models\TallasClub;
use App\Models\ValidacionCanje;
use App\Models\VariablesGlobales;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ProductosController extends BaseController
{
    public function crearProducto(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_producto_brimagy' => 'nullable',
                'nombre_producto' => 'required|string',
                'descripcion' => 'nullable|string',
                'marca' => 'nullable|string',
                'sku' => 'nullable|string',
                'color' => 'nullable|string',
                'talla' => 'nullable|string',
                'costo_con_iva' => 'required|integer',
                'costo_puntos_con_iva' => 'required|integer',
                'envio_extra' => 'required|integer',
                'tipo_registro' => 'required|string',
                'tipo_producto' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $id_proveedor = ($request->id_proveedor && $request->id_proveedor !== 'null')
                ? $request->id_proveedor
                : null;
            $nombreUnico = "";
            $id_catalogo = $request->id_catalogo;
            $id_plataforma = $request->id_plataforma;

            $plataforma = Plataformas::where('id', $id_plataforma)->first();

            if ($request->tipo_registro === 'excel') {
                if (empty($request->proveedor)) {
                    $id_proveedor = $request->id_proveedor ?? null;
                } else {
                    $proveedor = CatalogoProveedores::where('nombre', $request->proveedor)->first();
                    if (!$proveedor) {
                        DB::rollBack();
                        return $this->sendError('El proveedor "' . $request->proveedor . '" no existe', 'error', 404);
                    }
                    $id_proveedor = $proveedor->id;
                }

                // Buscar plataforma por nombre
                $plataforma = Plataformas::where('nombre', $request->nombre_plataforma)->first();
                if (!$plataforma) {
                    DB::rollBack();
                    return $this->sendError('La plataforma "' . $request->nombre_plataforma . '" no existe', 'error', 404);
                }
                $id_plataforma = $plataforma->id;

                // Buscar categoría por nombre
                if ($plataforma->nombre === "club bohn") {
                    $catalogo = SubCategoriaClub::where('desc', $request->catalogo)->first();
                } else {
                    $catalogo = SubCategoria::where('desc', $request->catalogo)->first();
                }

                if (!$catalogo) {
                    DB::rollBack();
                    return $this->sendError('La categoría "' . $request->catalogo . '" no existe', 'error', 404);
                }
                $id_catalogo = $catalogo->id;
            }

            if ($plataforma->nombre === "club bohn") {
                $productoExistente = CatalogoProductos::where('id_producto_brimagy', $request->id_producto_brimagy)->where('id_plataforma', 1)->first();
            } else {
                $productoExistente = CatalogoProductos::where('id_producto_brimagy', $request->id_producto_brimagy)->where('id_plataforma', 2)->first();
            }

            $variables = VariablesGlobales::where('id_plataforma', $id_plataforma)->first();

            $nombre_plataforma = $plataforma->nombre;

            if (!$variables) {
                $fee_brimagy = 0;
                $envio_base = 0;
                $costo_caja = 0;

                switch ($nombre_plataforma) {
                    case "club bohn":
                        $fee_brimagy = 15;
                        $envio_base = 180;
                        $costo_caja = 19;
                        break;
                    case "puntotes":
                        $fee_brimagy = 12;
                        $envio_base = 180;
                        $costo_caja = 19;
                        break;
                    default:
                        break;
                }
                $costo_proveedor_con_iva = $request->costo_con_iva;
                $costo_proveedor_sin_iva = $costo_proveedor_con_iva / 1.16;
                $costo_puntos_con_iva = $request->costo_puntos_con_iva;
                $costo_puntos_sin_iva = $costo_puntos_con_iva / 1.16;

                $envio_extra = $request->envio_extra;
                $valor_factor = $request->valor_factor;

                $porcentaje = (float) $fee_brimagy / 100;
                $valor_con_fee = (float) $costo_puntos_sin_iva * $porcentaje;
                $subtotal = $costo_puntos_sin_iva + $valor_con_fee;
                $total_envio = $envio_base + $costo_caja + $envio_extra;
                $total = $subtotal + $total_envio;
                $redondeo = ($total % 2 !== 0) ? $total + 2 : $total + 1; //puntos en bd
                $puntos = $valor_factor == 0 ? round($total) : round($total * $valor_factor); //factor en bd
            } else {
                $costo_proveedor_con_iva = $request->costo_con_iva;
                $costo_proveedor_sin_iva = $costo_proveedor_con_iva / 1.16;
                $costo_puntos_con_iva = $request->costo_puntos_con_iva;
                $costo_puntos_sin_iva = $costo_puntos_con_iva / 1.16;

                $fee_brimagy = $variables->fee_brimagy;
                $envio_base = $variables->envio_base;
                $costo_caja = $variables->costo_caja;
                $envio_extra = $variables->envio_extra;
                $valor_factor = $variables->factor;

                $porcentaje = (float) $fee_brimagy / 100;
                $valor_con_fee = (float) $costo_puntos_sin_iva * $porcentaje;
                $subtotal = $costo_puntos_sin_iva + $valor_con_fee;
                $total_envio = $envio_base + $costo_caja + $envio_extra;
                $total = $subtotal + $total_envio;
                $redondeo = ($total % 2 !== 0) ? $total + 2 : $total + 1; //puntos en bd
                $puntos = $valor_factor == 0 ? round($total)
                    : round($total * $valor_factor); //factor en bd
            }

            //Subir la foto
            $nombreUnico = "";
            $archivo = $request->file('foto_producto');

            if ($request->tipo_registro === 'individual' && $archivo) {
                switch ($nombre_plataforma) {
                    case "club bohn":
                        $sub_categoria = SubCategoriaClub::where('id', $request->id_catalogo)->first();
                        $categoria = CategoriaClub::where('id', $sub_categoria->category_id)->first();
                        break;
                    case "puntotes":
                        $sub_categoria = SubCategoria::where('id', $request->id_catalogo)->first();
                        $categoria = CatalogoCategoria::where('id', $sub_categoria->category_id)->first();
                        break;
                    default:
                        break;
                }

                $nombreOriginal = $archivo->getClientOriginalName();
                $extension = $archivo->getClientOriginalExtension();
                $nombreSinExtension = pathinfo($nombreOriginal, PATHINFO_FILENAME);
                $nombreUnico = $nombreSinExtension . now()->format('Y-m-d_H_i_s') . '.' . $extension;
            }

            if ($productoExistente) {

                $nombreUnico = $productoExistente->foto_producto;

                $valoresAnteriores = [
                    'fee_brimagy' => $productoExistente->fee_brimagy,
                    'envio_base' => $productoExistente->envio_base,
                    'costo_caja' => $productoExistente->costo_caja,
                    'envio_extra' => $productoExistente->envio_extra,
                    'subtotal' => $productoExistente->subtotal,
                    'total_envio' => $productoExistente->total_envio,
                    'total' => $productoExistente->total,
                    'puntos' => $productoExistente->puntos,
                    'valor_factor' => $productoExistente->valor_factor,
                    'factor' => $productoExistente->factor,
                    'costo_con_iva' => $productoExistente->costo_con_iva,
                    'costo_sin_iva' => $productoExistente->costo_sin_iva,
                    'costo_puntos_con_iva' => $productoExistente->costo_puntos_con_iva,
                    'costo_puntos_sin_iva' => $productoExistente->costo_puntos_sin_iva,
                ];

                $fee_brimagy = $variables ? $variables->fee_brimagy : ($request->fee_brimagy ?? $fee_brimagy);
                $envio_base = $variables ? $variables->envio_base : $envio_base;
                $costo_caja = $variables ? $variables->costo_caja : $costo_caja;
                $envio_extra = $variables ? $variables->envio_extra : ($request->envio_extra ?? $productoExistente->envio_extra);
                $valor_factor = $variables ? $variables->factor : ($request->valor_factor ?? $productoExistente->valor_factor);

                $costo_proveedor_con_iva = $request->costo_con_iva ?? $productoExistente->costo_con_iva;
                $costo_proveedor_sin_iva = $costo_proveedor_con_iva / 1.16;
                $costo_puntos_con_iva = $request->costo_puntos_con_iva ?? $productoExistente->costo_puntos_con_iva;
                $costo_puntos_sin_iva = $costo_puntos_con_iva / 1.16;

                $porcentaje = (float) $fee_brimagy / 100;
                $valor_con_fee = (float) $costo_puntos_sin_iva * $porcentaje;
                $subtotal = $costo_puntos_sin_iva + $valor_con_fee;
                $total_envio = $envio_base + $costo_caja + $envio_extra;
                $total = $subtotal + $total_envio;
                $redondeo = ($total % 2 !== 0) ? $total + 2 : $total + 1; //puntos en bd
                $puntos = $valor_factor == 0 ? round($total) : round($total * $valor_factor); //factor en bd

                $valoresNuevos = [
                    'fee_brimagy' => $fee_brimagy,
                    'envio_base' => $envio_base,
                    'costo_caja' => $costo_caja,
                    'envio_extra' => $envio_extra,
                    'subtotal' => $subtotal,
                    'total_envio' => $total_envio,
                    'total' => $total,
                    'puntos' => $redondeo,
                    'valor_factor' => $valor_factor,
                    'factor' => $puntos,
                    'costo_con_iva' => $costo_proveedor_con_iva,
                    'costo_sin_iva' => $costo_proveedor_sin_iva,
                    'costo_puntos_con_iva' => $costo_puntos_con_iva,
                    'costo_puntos_sin_iva' => $costo_puntos_sin_iva,
                ];

                $cambios = [];
                foreach ($valoresNuevos as $campo => $valorNuevo) {
                    $valorAnterior = $valoresAnteriores[$campo];
                    if ((string) $valorAnterior !== (string) $valorNuevo) {
                        $cambios[] = "{$campo}: {$valorAnterior} → {$valorNuevo}";
                    }
                }

                $productoExistente->update([
                    'nombre_producto' => $request->nombre_producto,
                    'descripcion' => $request->descripcion,
                    'marca' => $request->marca,
                    'color' => $request->color,
                    'talla' => $request->talla,
                    'id_proveedor' => $id_proveedor,
                    'id_catalogo' => $id_catalogo,
                    'costo_con_iva' => $costo_proveedor_con_iva,
                    'costo_sin_iva' => $costo_proveedor_sin_iva,
                    'costo_puntos_con_iva' => $costo_puntos_con_iva,
                    'costo_puntos_sin_iva' => $costo_puntos_sin_iva,
                    'fee_brimagy' => $fee_brimagy,
                    'subtotal' => $subtotal,
                    'envio_base' => $envio_base,
                    'costo_caja' => $costo_caja,
                    'envio_extra' => $envio_extra,
                    'total_envio' => $total_envio,
                    'total' => $total,
                    'puntos' => $redondeo,
                    'valor_factor' => $valor_factor,
                    'factor' => $puntos,
                    'foto_producto' => $nombreUnico,
                    'id_producto_brimagy' => $request->id_producto_brimagy,
                    'id_plataforma' => $id_plataforma,
                    'tipo_producto' => $request->tipo_producto,
                    'updated_at' => now()->setTimezone('America/Mexico_City'),
                ]);

                switch ($nombre_plataforma) {
                    case "club bohn":
                        $producto_brimagy = ProductoClub::find($productoExistente->id_producto_brimagy);
                        break;
                    case "puntotes":
                        $producto_brimagy = ProductoBrimagy::find($productoExistente->id_producto_brimagy);
                        break;
                    default:
                        break;
                }

                if (!$producto_brimagy) {
                    DB::rollBack();
                    return $this->sendError('No se encuentra el producto brimagy', 'error', 404);
                }

                $producto_brimagy->update([
                    'desc' => $request->nombre_producto,
                    'features' => $request->descripcion,
                    'required_score' => $puntos,
                    'sub_category_id' => $request->id_catalogo,
                    'photo_name' => $nombreUnico,
                    'sku' => $request->sku,
                    'TyC' => $request->tyc,
                    'validity' => $request->vigencia,
                ]);

                if ($request->tipo_registro === 'individual' && $archivo) {
                    $ruta = $archivo->storeAs(
                        'fotos_producto',
                        $nombreUnico,
                        'private'
                    );

                    Storage::disk('ftp_brimagy')->put(
                        $categoria->file_path . '/' . $nombreUnico,
                        file_get_contents($archivo->getRealPath())
                    );
                }

                $user = Auth::user();
                $descripcionCambios = !empty($cambios)
                    ? implode(' | ', $cambios)
                    : "Sin cambios en valores numéricos";

                BitacoraEventos::create([
                    'evento' => 'Edición de producto',
                    'tabla' => 'dc_catalogo_productos',
                    'id_referencia' => $request->id_producto,
                    'descripcion' => "Se editó la siguiente información del producto: {$descripcionCambios}",
                    'id_usuario' => $user->id,
                ]);

                DB::commit();
                return $this->sendResponse($productoExistente, 'Producto actualizado exitosamente.');
            }

            switch ($nombre_plataforma) {
                case "club bohn":
                    $producto_brimagy_existente = ProductoClub::find($request->id_producto_brimagy);

                    if (!$producto_brimagy_existente) {
                        $producto_brimagy = ProductoClub::create([
                            'desc' => $request->nombre_producto,
                            'features' => $request->descripcion,
                            'required_score' => $puntos,
                            'sub_category_id' => $request->id_catalogo,
                            'photo_name' => $nombreUnico,
                            'sku' => $request->sku,
                            'TyC' => $request->tyc,
                            'validity' => $request->vigencia,
                        ]);
                    }
                    break;
                case "puntotes":
                    $producto_brimagy_existente = ProductoBrimagy::find($request->id_producto_brimagy);

                    if (!$producto_brimagy_existente) {
                        $producto_brimagy = ProductoBrimagy::create([
                            'desc' => $request->nombre_producto,
                            'features' => $request->descripcion,
                            'required_score' => $puntos,
                            'sub_category_id' => $request->id_catalogo,
                            'photo_name' => $nombreUnico,
                            'sku' => $request->sku,
                            'TyC' => $request->tyc,
                            'validity' => $request->vigencia,
                        ]);
                    }
                    break;
                default:
                    break;
            }

            $producto = CatalogoProductos::create([
                'nombre_producto' => $request->nombre_producto,
                'descripcion' => $request->descripcion,
                'marca' => $request->marca,
                'sku' => $request->sku,
                'color' => $request->color,
                'talla' => $request->talla,
                'id_proveedor' => $id_proveedor,
                'id_catalogo' => $id_catalogo,
                'costo_con_iva' => $costo_proveedor_con_iva,
                'costo_sin_iva' => $costo_proveedor_sin_iva,
                'costo_puntos_con_iva' => $costo_puntos_con_iva,
                'costo_puntos_sin_iva' => $costo_puntos_sin_iva,
                'fee_brimagy' => $fee_brimagy,
                'subtotal' => $subtotal,
                'envio_base' => $envio_base,
                'costo_caja' => $costo_caja,
                'envio_extra' => $envio_extra,
                'total_envio' => $total_envio,
                'total' => $total,
                'puntos' => $redondeo,
                'valor_factor' => $valor_factor,
                'factor' => $puntos,
                'foto_producto' => $nombreUnico,
                'id_producto_brimagy' => $producto_brimagy->id ?? $producto_brimagy_existente->id,
                'id_plataforma' => $id_plataforma,
                'tipo_producto' => $request->tipo_producto,
            ]);

            if ($request->filled('color')) {

                switch ($nombre_plataforma) {
                    case "club bohn":
                        $color_brimagy = ColoresClub::create([
                            'award_id' => $producto_brimagy->id ?? $producto_brimagy_existente->id,
                            'color' => $request->color,
                            'status' => "ACTIVE",
                        ]);
                        break;
                    case "puntotes":
                        $color_brimagy = ColoresBrimagy::create([
                            'award_id' => $producto_brimagy->id ?? $producto_brimagy_existente->id,
                            'color' => $request->color,
                            'status' => "ACTIVE",
                        ]);
                        break;
                    default:
                        break;
                }

                Colores::create([
                    'id_producto' => $producto->id,
                    'id_color_brimagy' => $color_brimagy->id,
                    'color' => $request->color,
                    'status' => "ACTIVE",
                ]);
            }
            if ($request->filled('talla')) {

                switch ($nombre_plataforma) {
                    case "club bohn":
                        $talla_brimagy = TallasClub::create([
                            'award_id' => $producto_brimagy->id ?? $producto_brimagy_existente->id,
                            'size' => $request->talla,
                            'status' => "ACTIVE",
                        ]);
                        break;
                    case "puntotes":
                        $talla_brimagy = TallasBrimagy::create([
                            'award_id' => $producto_brimagy->id ?? $producto_brimagy_existente->id,
                            'size' => $request->talla,
                            'status' => "ACTIVE",
                        ]);
                        break;
                    default:
                        break;
                }

                Tallas::create([
                    'id_producto' => $producto->id,
                    'id_talla_brimagy' => $talla_brimagy->id,
                    'talla' => $request->talla,
                    'status' => "ACTIVE",
                ]);
            }

            if ($request->tipo_registro === 'individual' && $archivo) {
                $ruta = $archivo->storeAs(
                    'fotos_producto',
                    $nombreUnico,
                    'private'
                );

                Storage::disk('ftp_brimagy')->put(
                    $categoria->file_path . '/' . $nombreUnico,
                    file_get_contents($archivo->getRealPath())
                );
            }

            $user = Auth::user();
            $log['evento'] = 'Creación de producto';
            $log['descripcion'] = "El usuario con id: {$user->id} añadio el producto con id: {$producto->id} al catalogo";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($producto, 'Producto registrado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar el producto', $th->getMessage(), 500);
        }
    }
    public function crearEditarColorProducto(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_producto_brimagy' => 'nullable|integer',
                'id_producto_dirac' => 'nullable|integer',
                'id_color_brimagy' => 'nullable|integer',
                'id_color' => 'nullable|integer',
                'color' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            if ($request->plataforma === 'club_bohn') {
                $colorExistente = Colores::where('id', $request->id_color)->first();
                $colorExistenteBrimagy = ColoresClub::where('id', $request->id_color_brimagy)->first();
            } else {
                $colorExistente = Colores::where('id', $request->id_color)->first();
                $colorExistenteBrimagy = ColoresBrimagy::where('id', $request->id_color_brimagy)->first();
            }

            if ($colorExistente) {

                $colorExistenteNombre = Colores::where('color', $request->color)
                    ->where('id_producto', $request->id_producto_dirac)
                    ->first();
                if ($colorExistenteNombre) {
                    DB::rollBack();
                    return $this->sendError('El color ' . $request->color . ' ya existe', 'error', 500);
                }

                $valoresAnteriores = [
                    'color' => $colorExistente->color,
                    'status' => $colorExistente->status,
                ];
                $valoresNuevos = [
                    'color' => $request->color,
                    'status' => $request->status,
                ];

                $colorExistente->update([
                    'color' => $request->color,
                    'status' => "ACTIVE",
                    'updated_at' => now()->setTimezone('America/Mexico_City'),
                ]);
                $colorExistenteBrimagy->update([
                    'color' => $request->color,
                    'status' => "ACTIVE",
                    'updated_at' => now()->setTimezone('America/Mexico_City'),
                ]);

                $user = Auth::user();
                $cambios = [];
                foreach ($valoresNuevos as $campo => $valorNuevo) {
                    $valorAnterior = $valoresAnteriores[$campo];
                    if ((string) $valorAnterior !== (string) $valorNuevo) {
                        $cambios[] = "{$campo}: {$valorAnterior} → {$valorNuevo}";
                    }
                }
                $descripcionCambios = !empty($cambios)
                    ? implode(' | ', $cambios)
                    : "Sin cambios";

                BitacoraEventos::create([
                    'evento' => 'Edición de producto',
                    'tabla' => 'dc_colores_premio',
                    'id_referencia' => $request->id_producto_dirac,
                    'descripcion' => "Se editó la siguiente información del producto: {$descripcionCambios}",
                    'id_usuario' => $user->id,
                ]);

                DB::commit();
                return $this->sendResponse($colorExistente, 'Color editado exitosamente.');
            }

            $colorExistenteNombre = Colores::where('color', $request->color)
                ->where('id_producto', $request->id_producto_dirac)
                ->first();

            if ($colorExistenteNombre) {
                DB::rollBack();
                return $this->sendError('El color ' . $request->color . ' ya existe', 'error', 500);
            }

            if ($request->plataforma === 'club_bohn') {
                $productoBrimagy = ColoresClub::create([
                    'award_id' => $request->id_producto_brimagy,
                    'color' => $request->color,
                    'status' => "ACTIVE",
                ]);
            } else {
                $productoBrimagy = ColoresBrimagy::create([
                    'award_id' => $request->id_producto_brimagy,
                    'color' => $request->color,
                    'status' => "ACTIVE",
                ]);
            }

            $producto = Colores::create([
                'id_producto' => $request->id_producto_dirac,
                'id_color_brimagy' => $productoBrimagy->id,
                'color' => $request->color,
                'status' => "ACTIVE",
            ]);

            $user = Auth::user();
            $log['evento'] = 'Creación de color';
            $log['id_referencia'] = $request->id_producto_dirac;
            $log['descripcion'] = "El usuario con id: {$user->id} añadio el color con: {$request->color} al producto {$request->id_producto_dirac}";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($producto, 'Color registrado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar el color', $th->getMessage(), 500);
        }
    }
    public function getProductoColorPorId(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el id del color.', $validator->errors());
            }

            $query = DB::table('dc_colores_premio as cp')
                ->select(
                    'cp.id',
                    'cp.id_color_brimagy',
                    'cp.color',
                    'cp.status',
                )
                ->where('cp.id_producto', $request->id_producto);

            $colores = $query->orderBy('cp.created_at', 'desc')->get();

            return $this->sendResponse($colores);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los colores', $th, 500);
        }
    }
    public function desactivarColorProducto(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_color' => 'required|integer',
                'id_color_brimagy' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $color = Colores::find($request->id_color);
            if ($request->plataforma === "club_bohn") {
                $color_brimagy = ColoresClub::find($request->id_color_brimagy);
            } else {
                $color_brimagy = ColoresBrimagy::find($request->id_color_brimagy);
            }

            if (!$color) {
                DB::rollBack();
                return $this->sendError('Este color no se encuentra', 'error', 404);
            }
            if (!$color_brimagy) {
                DB::rollBack();
                return $this->sendError('Este color no se encuentra', 'error', 404);
            }

            $color->update([
                'status' => "INACTIVE",
            ]);
            $color_brimagy->update([
                'status' => "INACTIVE",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se desactivó un color de producto',
                'descripcion' => "El usuario con id: {$user->id} desactivó el color {$color->color}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($color, 'Color desactivado correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al desactivar el color', $th->getMessage(), 500);
        }
    }
    public function activarColorProducto(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_color' => 'required|integer',
                'id_color_brimagy' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $color = Colores::find($request->id_color);
            if ($request->plataforma === "club_bohn") {
                $color_brimagy = ColoresClub::find($request->id_color_brimagy);
            } else {
                $color_brimagy = ColoresBrimagy::find($request->id_color_brimagy);
            }

            if (!$color) {
                DB::rollBack();
                return $this->sendError('Este color no se encuentra', 'error', 404);
            }
            if (!$color_brimagy) {
                DB::rollBack();
                return $this->sendError('Este color no se encuentra', 'error', 404);
            }

            $color->update([
                'status' => "ACTIVE",
            ]);
            $color_brimagy->update([
                'status' => "ACTIVE",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se activó un color de producto',
                'descripcion' => "El usuario con id: {$user->id} activó el color {$color->color}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($color, 'Color activado correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al activar el color', $th->getMessage(), 500);
        }
    }
    //tallas
    public function crearEditarTallaProducto(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_producto_brimagy' => 'nullable|integer',
                'id_producto_dirac' => 'nullable|integer',
                'id_talla_brimagy' => 'nullable|integer',
                'id_talla' => 'nullable|integer',
                'talla' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $tallaExistente = Tallas::where('id', $request->id_talla)->first();

            if ($request->plataforma === "club_bohn") {
                $tallaExistenteBrimagy = TallasClub::where('id', $request->id_talla_brimagy)->first();
            } else {
                $tallaExistenteBrimagy = TallasBrimagy::where('id', $request->id_talla_brimagy)->first();
            }

            if ($tallaExistente) {

                $tallaExistenteNombre = Tallas::where('talla', $request->talla)
                    ->where('id_producto', $request->id_producto_dirac)
                    ->first();
                if ($tallaExistenteNombre) {
                    DB::rollBack();
                    return $this->sendError('La talla ' . $request->talla . ' ya existe', 'error', 500);
                }

                $valoresAnteriores = [
                    'talla' => $tallaExistente->color,
                    'status' => $tallaExistente->status,
                ];
                $valoresNuevos = [
                    'talla' => $request->color,
                    'status' => $request->status,
                ];

                $tallaExistente->update([
                    'talla' => $request->talla,
                    'status' => "ACTIVE",
                    'updated_at' => now()->setTimezone('America/Mexico_City'),
                ]);
                $tallaExistenteBrimagy->update([
                    'size' => $request->talla,
                    'status' => "ACTIVE",
                    'updated_at' => now()->setTimezone('America/Mexico_City'),
                ]);

                $user = Auth::user();
                $cambios = [];
                foreach ($valoresNuevos as $campo => $valorNuevo) {
                    $valorAnterior = $valoresAnteriores[$campo];
                    if ((string) $valorAnterior !== (string) $valorNuevo) {
                        $cambios[] = "{$campo}: {$valorAnterior} → {$valorNuevo}";
                    }
                }
                $descripcionCambios = !empty($cambios)
                    ? implode(' | ', $cambios)
                    : "Sin cambios";

                BitacoraEventos::create([
                    'evento' => 'Edición de producto',
                    'tabla' => 'dc_tallas_premio',
                    'id_referencia' => $request->id_producto_dirac,
                    'descripcion' => "Se editó la siguiente información del producto: {$descripcionCambios}",
                    'id_usuario' => $user->id,
                ]);

                DB::commit();
                return $this->sendResponse($tallaExistente, 'Talla editada exitosamente.');
            }

            $tallaExistenteNombre = Tallas::where('talla', $request->talla)
                ->where('id_producto', $request->id_producto_dirac)
                ->first();

            if ($tallaExistenteNombre) {
                DB::rollBack();
                return $this->sendError('La talla ' . $request->talla . ' ya existe', 'error', 500);
            }

            if ($request->plataforma === "club_bohn") {
                $tallaBrimagy = TallasClub::create([
                    'award_id' => $request->id_producto_brimagy,
                    'size' => $request->talla,
                    'status' => "ACTIVE",
                ]);
            } else {
                $tallaBrimagy = TallasBrimagy::create([
                    'award_id' => $request->id_producto_brimagy,
                    'size' => $request->talla,
                    'status' => "ACTIVE",
                ]);
            }

            $talla = Tallas::create([
                'id_producto' => $request->id_producto_dirac,
                'id_talla_brimagy' => $tallaBrimagy->id,
                'talla' => $request->talla,
                'status' => "ACTIVE",
            ]);

            $user = Auth::user();
            $log['evento'] = 'Creación de talla';
            $log['id_referencia'] = $request->id_producto_dirac;
            $log['descripcion'] = "El usuario con id: {$user->id} añadio la talla: {$request->talla} al producto {$request->id_producto_dirac}";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($talla, 'Talla registrada exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar la talla', $th->getMessage(), 500);
        }
    }
    public function getProductoTallaPorId(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el id de la talla.', $validator->errors());
            }

            $query = DB::table('dc_tallas_premio as tp')
                ->select(
                    'tp.id',
                    'tp.id_talla_brimagy',
                    'tp.talla',
                    'tp.status',
                )
                ->where('tp.id_producto', $request->id_producto);

            $tallas = $query->orderBy('tp.created_at', 'desc')->get();

            return $this->sendResponse($tallas);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener las tallas', $th, 500);
        }
    }
    public function desactivarTallaProducto(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_talla' => 'required|integer',
                'id_talla_brimagy' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $talla = Tallas::find($request->id_talla);

            if ($request->plataforma === "club_bohn") {
                $talla_brimagy = TallasClub::find($request->id_talla_brimagy);
            } else {
                $talla_brimagy = TallasBrimagy::find($request->id_talla_brimagy);
            }


            if (!$talla) {
                DB::rollBack();
                return $this->sendError('Esta talla no se encuentra', 'error', 404);
            }
            if (!$talla_brimagy) {
                DB::rollBack();
                return $this->sendError('Esta talla no se encuentra', 'error', 404);
            }

            $talla->update([
                'status' => "INACTIVE",
            ]);
            $talla_brimagy->update([
                'status' => "INACTIVE",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se desactivó una talla de producto',
                'descripcion' => "El usuario con id: {$user->id} desactivó la talla {$talla->talla}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($talla, 'Talla desactivada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al desactivar la talla', $th->getMessage(), 500);
        }
    }
    public function activarTallaProducto(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_talla' => 'required|integer',
                'id_talla_brimagy' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $talla = Tallas::find($request->id_talla);

            if ($request->plataforma === "club_bohn") {
                $talla_brimagy = TallasClub::find($request->id_talla_brimagy);
            } else {
                $talla_brimagy = TallasBrimagy::find($request->id_talla_brimagy);
            }

            if (!$talla) {
                DB::rollBack();
                return $this->sendError('Esta talla no se encuentra', 'error', 404);
            }
            if (!$talla_brimagy) {
                DB::rollBack();
                return $this->sendError('Esta talla no se encuentra', 'error', 404);
            }

            $talla->update([
                'status' => "ACTIVE",
            ]);
            $talla_brimagy->update([
                'status' => "ACTIVE",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se activó una talla de producto',
                'descripcion' => "El usuario con id: {$user->id} activó la talla {$talla->talla}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($talla, 'Talla activada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al activar la talla', $th->getMessage(), 500);
        }
    }
    public function registrarNuevoPrecio(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'costo_con_iva' => 'required|integer',
                'id_producto' => 'required|integer',
                'id_validacion' => 'required|integer',
                'id_proveedor' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $datosProducto = CatalogoProductos::where('id', $request->id_producto)->first();

            //datos del producto existente
            $nombre_producto = $datosProducto->nombre_producto;
            $descripcion = $datosProducto->descripcion;
            $marca = $datosProducto->marca;
            $sku = $datosProducto->sku;
            $color = $datosProducto->color;
            $talla = $datosProducto->talla;
            $id_proveedor = $request->id_proveedor;
            $id_catalogo = $datosProducto->id_catalogo;
            $costo_con_iva = $request->costo_con_iva;
            $costo_sin_iva = $request->costo_con_iva / 1.16;
            $costo_puntos_con_iva = $datosProducto->costo_puntos_con_iva;
            $costo_puntos_sin_iva = $datosProducto->costo_puntos_sin_iva;
            $fee_brimagy = $datosProducto->fee_brimagy;
            $subtotal = $datosProducto->subtotal;
            $envio_base = $datosProducto->envio_base;
            $costo_caja = $datosProducto->costo_caja;
            $envio_extra = $datosProducto->envio_extra;
            $total_envio = $datosProducto->total_envio;
            $total = $datosProducto->total;
            $puntos = $datosProducto->puntos;
            $valor_factor = $datosProducto->valor_factor;
            $factor = $datosProducto->factor;
            $foto_producto = $datosProducto->foto_producto;
            $id_plataforma = $datosProducto->id_plataforma;
            $tipo_producto = $datosProducto->tipo_producto;

            if ($request->plataforma === "club_bohn") {
                $producto_brimagy = ProductoClub::create([
                    'desc' => $nombre_producto,
                    'features' => $descripcion,
                    'required_score' => $puntos,
                    'sub_category_id' => $id_catalogo,
                    'photo_name' => $foto_producto,
                    'sku' => $sku,
                    'TyC' => "",
                    'validity' => "",
                ]);
            } else {
                $producto_brimagy = ProductoBrimagy::create([
                    'desc' => $nombre_producto,
                    'features' => $descripcion,
                    'required_score' => $puntos,
                    'sub_category_id' => $id_catalogo,
                    'photo_name' => $foto_producto ?: '',
                    'sku' => $sku,
                    'TyC' => "",
                    'validity' => "",
                ]);
            }

            $producto = CatalogoProductos::create([
                'nombre_producto' => $nombre_producto,
                'descripcion' => $descripcion,
                'marca' => $marca,
                'sku' => $sku,
                'color' => $color,
                'talla' => $talla,
                'id_proveedor' => $id_proveedor,
                'id_catalogo' => $id_catalogo,
                'costo_con_iva' => $costo_con_iva,
                'costo_sin_iva' => $costo_sin_iva,
                'costo_puntos_con_iva' => $costo_puntos_con_iva,
                'costo_puntos_sin_iva' => $costo_puntos_sin_iva,
                'fee_brimagy' => $fee_brimagy,
                'subtotal' => $subtotal,
                'envio_base' => $envio_base,
                'costo_caja' => $costo_caja,
                'envio_extra' => $envio_extra,
                'total_envio' => $total_envio,
                'total' => $total,
                'puntos' => $puntos,
                'valor_factor' => $valor_factor,
                'factor' => $factor,
                'foto_producto' => $foto_producto,
                'id_producto_brimagy' => $producto_brimagy->id,
                'id_plataforma' => $id_plataforma,
                'tipo_producto' => $tipo_producto,
            ]);

            $datosParaActualizar = [
                'id_producto' => $producto->id,
                'id_proveedor' => $id_proveedor ?? null,
                'updated_at' => now()->setTimezone('America/Mexico_City'),
            ];

            $datosParaActualizar = array_filter($datosParaActualizar, function ($value) {
                return !is_null($value) && $value !== '';
            });

            $validacionCanje = ValidacionCanje::where('id', $request->id_validacion)
                ->update($datosParaActualizar);

            $user = Auth::user();
            $log['evento'] = 'Creación de producto';
            $log['descripcion'] = "El usuario con id: {$user->id} añadio el producto con id: {$producto->id} al catalogo";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($producto, 'Producto registrado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar el producto', $th->getMessage(), 500);
        }
    }

    public function verificarSkus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'skus' => 'required|array',
                'skus.*' => 'string'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Formato de datos no válido', $validator->errors());
            }

            $skusValidos = array_filter($request->skus, function ($sku) {
                return !empty($sku) && strtoupper(trim($sku)) !== 'N/A';
            });

            $skusExistentes = [];
            if (!empty($skusValidos)) {
                $skusExistentes = CatalogoProductos::whereIn('sku', $skusValidos)
                    ->pluck('sku')
                    ->toArray();
            }

            return $this->sendResponse([
                'skus_existentes' => $skusExistentes
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al verificar SKUs', $th->getMessage(), 500);
        }
    }

    public function verificarIdProductoBrimagy(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array',
                'ids.*' => 'string|nullable'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Formato de datos no válido', $validator->errors());
            }

            $idValidos = array_filter($request->ids, function ($id) {
                return !empty($id) && strtoupper(trim($id)) !== 'N/A';
            });

            $idsExistentes = [];
            if (!empty($idValidos)) {
                $idsExistentes = CatalogoProductos::whereIn('id_producto_brimagy', $idValidos)
                    ->pluck('id_producto_brimagy')
                    ->toArray();
            }

            return $this->sendResponse([
                'ids_existentes' => $idsExistentes
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al verificar SKUs', $th->getMessage(), 500);
        }
    }

    public function verificarSkuDisponible(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'sku' => 'required|string'
            ]);

            if ($validator->fails()) {
                return $this->sendError('SKU es requerido', $validator->errors());
            }

            $productoExistente = CatalogoProductos::where('sku', $request->sku)->first();

            return $this->sendResponse([
                'disponible' => !$productoExistente,
                'producto_existente' => $productoExistente ? [
                    'id' => $productoExistente->id,
                    'nombre' => $productoExistente->nombre_producto
                ] : null
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al verificar SKU', $th->getMessage(), 500);
        }
    }

    public function getCatalogoProductos(Request $request)
    {
        try {
            $esClubBohn = $request->plataforma === 'club_bohn';

            $query = DB::table('dc_catalogo_productos as cpt')
                ->select(
                    'cpt.id',
                    'cpt.nombre_producto',
                    'cpt.descripcion',
                    'cpt.marca',
                    'cpt.sku',
                    'cpt.color',
                    'cpt.talla',
                    'cpv.nombre as proveedor',
                    'cpt.id_catalogo',
                    'cpt.costo_con_iva',
                    'cpt.costo_sin_iva',
                    'cpt.costo_puntos_con_iva',
                    'cpt.costo_puntos_sin_iva',
                    'cpt.fee_brimagy',
                    'cpt.subtotal',
                    'cpt.envio_base',
                    'cpt.costo_caja',
                    'cpt.envio_extra',
                    'cpt.total_envio',
                    'cpt.total',
                    'cpt.puntos',
                    'cpt.valor_factor',
                    'cpt.factor',
                    'cpt.foto_producto',
                    'cpt.id_producto_brimagy',
                    'cpt.tipo_producto',
                    'cpt.stock',
                    'p.nombre as nombre_plataforma',
                    'cpt.created_at as fecha_creacion',
                )
                ->leftJoin('dc_plataformas as p', 'cpt.id_plataforma', '=', 'p.id')
                ->leftJoin('dc_catalogo_proveedores as cpv', 'cpt.id_proveedor', '=', 'cpv.id')
                ->when(!$esClubBohn, function ($q) {
                    $q->leftJoin('sub_categories as sc', 'cpt.id_catalogo', '=', 'sc.id')
                        ->addSelect('sc.desc as catalogo');
                })
                ->when($request->tipo_producto && $request->tipo_producto !== 'todos', function ($q) use ($request) {
                    $q->where('cpt.tipo_producto', $request->tipo_producto);
                });

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search, $esClubBohn) {
                    $q->where('cpt.nombre_producto', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.descripcion', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.marca', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.sku', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.color', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.talla', 'LIKE', "%{$search}%")
                        ->orWhere('cpv.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.puntos', 'LIKE', "%{$search}%");

                    if (!$esClubBohn) {
                        $q->orWhere('sc.desc', 'LIKE', "%{$search}%");
                    }
                });
            }

            // BÚSQUEDA POR FECHAS
            if (
                $request->has('fecha1') && !empty($request->fecha1) &&
                $request->has('fecha2') && !empty($request->fecha2)
            ) {
                $fecha1 = Carbon::parse($request->fecha1);
                $fecha2 = Carbon::parse($request->fecha2);

                if ($fecha1->lt($fecha2)) {
                    $inicio = $fecha1->copy()->startOfDay();
                    $fin = $fecha2->copy()->endOfDay();
                } else {
                    $inicio = $fecha2->copy()->startOfDay();
                    $fin = $fecha1->copy()->endOfDay();
                }

                $query->whereBetween('cpt.created_at', [$inicio, $fin]);
            }

            // PLATAFORMA
            if ($request->has('plataforma') && !empty($request->plataforma)) {
                $plataforma = $esClubBohn ? 'club bohn' : $request->plataforma;
                $plataformaModel = Plataformas::where('nombre', $plataforma)->first();

                if (!$plataformaModel) {
                    return $this->sendError('La plataforma "' . $plataforma . '" no existe', 'error', 404);
                }

                $query->where('cpt.id_plataforma', $plataformaModel->id);
            }

            $productos = $query->orderBy('cpt.created_at', 'desc')->get();

            if ($esClubBohn) {
                $idsCatalogo = $productos->pluck('id_catalogo')->filter()->unique()->values();

                $subCategorias = SubCategoriaClub::whereIn('id', $idsCatalogo)
                    ->pluck('desc', 'id');

                $productos = $productos->map(function ($producto) use ($subCategorias) {
                    $producto->catalogo = $subCategorias[$producto->id_catalogo] ?? null;
                    return $producto;
                });
            }

            return $this->sendResponse($productos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }

    public function getCatalogoProductosFisicos(Request $request)
    {
        try {
            $query = DB::table('dc_catalogo_productos as cpt')
                ->select(
                    'cpt.id',
                    'cpt.nombre_producto',
                    'cpt.descripcion',
                    'cpt.marca',
                    'cpt.sku',
                    'cpt.color',
                    'cpt.talla',
                    'cpv.nombre as proveedor',
                    'ac.desc as catalogo',
                    'cpt.costo_con_iva',
                    'cpt.costo_sin_iva',
                    'cpt.costo_puntos_con_iva',
                    'cpt.costo_puntos_sin_iva',
                    'cpt.fee_brimagy',
                    'cpt.subtotal',
                    'cpt.envio_base',
                    'cpt.costo_caja',
                    'cpt.envio_extra',
                    'cpt.total_envio',
                    'cpt.total',
                    'cpt.puntos',
                    'cpt.valor_factor',
                    'cpt.factor',
                    'cpt.tipo_producto',
                    'p.nombre as nombre_plataforma',
                    'cpt.created_at as fecha_creacion',
                )
                ->leftJoin('awards_categories as ac', 'cpt.id_catalogo', '=', 'ac.id')
                ->leftJoin('dc_plataformas as p', 'cpt.id_plataforma', '=', 'p.id')
                ->leftJoin('dc_catalogo_proveedores as cpv', 'cpt.id_proveedor', '=', 'cpv.id')
                ->where('cpt.tipo_producto', '=', 'fisico');

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('cpt.nombre_producto', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.descripcion', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.marca', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.sku', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.color', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.talla', 'LIKE', "%{$search}%")
                        ->orWhere('cpv.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.puntos', 'LIKE', "%{$search}%")
                        ->orWhere('ac.desc', 'LIKE', "%{$search}%");
                });
            }
            // BÚSQUEDA POR FECHAS
            if (($request->has('fecha1') && !empty($request->fecha1)) && ($request->has('fecha2') && !empty($request->fecha2))) {
                $fecha1 = Carbon::parse($request->fecha1)->startOfDay();
                $fecha2 = Carbon::parse($request->fecha2)->endOfDay();
                // Ordenar para que siempre la menor sea el inicio
                $inicio = $fecha1->lt($fecha2) ? $fecha1->startOfDay() : $fecha2->startOfDay();
                $fin = $fecha1->lt($fecha2) ? $fecha2->endOfDay() : $fecha1->endOfDay();

                $query->whereBetween('cpt.created_at', [$inicio, $fin]);
            }

            $productos = $query->orderBy('cpt.created_at', 'desc')->get();

            return $this->sendResponse($productos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }

    public function getCatalogoProductosDigitales(Request $request)
    {
        try {
            $query = DB::table('dc_catalogo_productos as cpt')
                ->select(
                    'cpt.id',
                    'cpt.nombre_producto',
                    'cpt.descripcion',
                    'cpt.marca',
                    'cpt.sku',
                    'cpt.color',
                    'cpt.talla',
                    'cpv.nombre as proveedor',
                    'ac.desc as catalogo',
                    'cpt.costo_con_iva',
                    'cpt.costo_sin_iva',
                    'cpt.costo_puntos_con_iva',
                    'cpt.costo_puntos_sin_iva',
                    'cpt.fee_brimagy',
                    'cpt.subtotal',
                    'cpt.envio_base',
                    'cpt.costo_caja',
                    'cpt.envio_extra',
                    'cpt.total_envio',
                    'cpt.total',
                    'cpt.puntos',
                    'cpt.factor',
                    'cpt.tipo_producto',
                    'p.nombre as nombre_plataforma',
                    'cpt.created_at as fecha_creacion',
                )
                ->leftJoin('awards_categories as ac', 'cpt.id_catalogo', '=', 'ac.id')
                ->leftJoin('dc_plataformas as p', 'cpt.id_plataforma', '=', 'p.id')
                ->leftJoin('dc_catalogo_proveedores as cpv', 'cpt.id_proveedor', '=', 'cpv.id')
                ->where('cpt.tipo_producto', '=', 'digital');

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('cpt.nombre_producto', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.descripcion', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.marca', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.sku', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.color', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.talla', 'LIKE', "%{$search}%")
                        ->orWhere('cpv.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.puntos', 'LIKE', "%{$search}%")
                        ->orWhere('ac.desc', 'LIKE', "%{$search}%");
                });
            }
            // BÚSQUEDA POR FECHAS
            if (($request->has('fecha1') && !empty($request->fecha1)) && ($request->has('fecha2') && !empty($request->fecha2))) {
                $fecha1 = Carbon::parse($request->fecha1)->startOfDay();
                $fecha2 = Carbon::parse($request->fecha2)->endOfDay();
                // Ordenar para que siempre la menor sea el inicio
                $inicio = $fecha1->lt($fecha2) ? $fecha1->startOfDay() : $fecha2->startOfDay();
                $fin = $fecha1->lt($fecha2) ? $fecha2->endOfDay() : $fecha1->endOfDay();

                $query->whereBetween('cpt.created_at', [$inicio, $fin]);
            }

            $productos = $query->orderBy('cpt.created_at', 'desc')->get();

            return $this->sendResponse($productos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }

    public function editarProducto(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer|exists:dc_catalogo_productos,id'
            ]);
            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id del producto.', $validator->errors());
            }

            $producto = CatalogoProductos::find($request->id_producto);
            if (!$producto) {
                DB::rollBack();
                return $this->sendError('Este producto no existe', [], 404);
            }

            $camposAuditables = [
                'fee_brimagy',
                'envio_base',
                'costo_caja',
                'envio_extra',
                'subtotal',
                'total_envio',
                'total',
                'puntos',
                'valor_factor',
                'factor',
                'costo_con_iva',
                'costo_sin_iva',
                'costo_puntos_con_iva',
                'costo_puntos_sin_iva',
            ];
            $valoresAnteriores = $producto->only($camposAuditables);

            $datosParaActualizar = $request->only([
                'nombre_producto',
                'descripcion',
                'marca',
                'sku',
                'color',
                'talla',
                'id_proveedor',
                'id_catalogo',
                'costo_con_iva',
                'costo_puntos_con_iva',
                'envio_extra',
                'valor_factor',
                'foto_producto',
            ]);

            $datosParaActualizar = array_filter($datosParaActualizar, function ($value, $campo) use ($producto) {
                return (string) $producto->$campo !== (string) $value;
            }, ARRAY_FILTER_USE_BOTH);

            if (empty($datosParaActualizar)) {
                DB::rollBack();
                return $this->sendResponse("No hay cambios que guardar.");
            }

            $camposQueRecalculan = ['fee_brimagy', 'envio_base', 'costo_caja', 'envio_extra', 'costo_puntos_con_iva'];
            $debeRecalcular = count(array_intersect($camposQueRecalculan, array_keys($datosParaActualizar))) > 0;

            $plataforma = Plataformas::where('nombre', $request->nombre_plataforma)->first();
            if (!$plataforma) {
                DB::rollBack();
                return $this->sendError('La plataforma "' . $request->nombre_plataforma . '" no existe', 'error', 404);
            }

            $variables = VariablesGlobales::where('id_plataforma', $plataforma->id)->first();

            $plataforma = Plataformas::where('id', $producto->id_plataforma)->first();
            $nombre_plataforma = $plataforma->nombre;

            $fee_brimagy = 0;
            $envio_base_g = 0;
            $costo_caja_g = 0;

            switch ($nombre_plataforma) {
                case "club bohn":
                    $fee_brimagy = 15;
                    $envio_base_g = 180;
                    $costo_caja_g = 19;
                    break;
                case "puntotes":
                    $fee_brimagy = 12;
                    $envio_base_g = 180;
                    $costo_caja_g = 19;
                    break;
                default:
                    break;
            }

            $costo_proveedor_con_iva = $request->has('costo_con_iva')
                ? ($request->costo_con_iva === '' || $request->costo_con_iva === null ? 0 : $request->costo_con_iva)
                : $producto->costo_con_iva;
            $costo_proveedor_sin_iva = $costo_proveedor_con_iva > 0
                ? $costo_proveedor_con_iva / 1.16
                : 0;

            $costo_puntos_con_iva = $request->has('costo_puntos_con_iva')
                ? ($request->costo_puntos_con_iva === '' || $request->costo_puntos_con_iva === null ? 0 : $request->costo_puntos_con_iva)
                : $producto->costo_puntos_con_iva;
            $costo_puntos_sin_iva = $costo_puntos_con_iva > 0
                ? $costo_puntos_con_iva / 1.16
                : 0;

            $valorDelRequest = function (string $campo) use ($request, $producto) {
                return $request->has($campo)
                    ? ($request->$campo === '' || $request->$campo === null ? 0 : $request->$campo)
                    : $producto->$campo;
            };

            $fee_brimagy = $variables ? $variables->fee_brimagy : $fee_brimagy;
            $envio_base = $variables ? $variables->envio_base : $envio_base_g;
            $costo_caja = $variables ? $variables->costo_caja : $costo_caja_g;

            $envio_extra = $valorDelRequest('envio_extra');
            $valor_factor = $valorDelRequest('valor_factor');

            $porcentaje = (float) $fee_brimagy / 100;
            $valor_con_fee = (float) $costo_puntos_sin_iva * $porcentaje;
            $subtotal = $costo_puntos_sin_iva + $valor_con_fee;
            $total_envio = $envio_base + $costo_caja + $envio_extra;
            $total = $subtotal + $total_envio;
            $redondeo = ($total % 2 !== 0) ? $total + 2 : $total + 1; // puntos en bd
            $puntos = $valor_factor == 0 ? round($total) : round($total * $valor_factor); // factor en bd

            //Subir la foto
            $nombreUnico = $producto->foto_producto;
            $archivo = $request->file('foto_producto');

            if (
                ($request->has('envio_extra') && $request->envio_extra !== '' && $request->envio_extra !== null)
                ||
                ($request->has('valor_factor') && $request->valor_factor !== '' && $request->valor_factor !== null)
            ) {
                $existeEditando = ProductoEditado::where('id_producto', $producto->id)->first();
                if (!$existeEditando) {
                    ProductoEditado::create([
                        'id_producto' => $producto->id,
                        'editado' => 1
                    ]);
                }
            }

            switch ($nombre_plataforma) {
                case "club bohn":
                    // Obtener categoría anterior
                    $sub_categoria_anterior = SubCategoriaClub::where('id', $producto->id_catalogo)->first();
                    $categoria_anterior = CategoriaClub::where('id', $sub_categoria_anterior->category_id)->first();

                    // Obtener nueva categoría
                    $sub_categoria_nueva = SubCategoriaClub::where('id', $request->id_catalogo ?? $producto->id_catalogo)->first();
                    $categoria_nueva = CategoriaClub::where('id', $sub_categoria_nueva->category_id)->first();
                    break;
                case "puntotes":
                    // Obtener categoría anterior
                    $sub_categoria_anterior = SubCategoria::where('id', $producto->id_catalogo)->first();
                    $categoria_anterior = CatalogoCategoria::where('id', $sub_categoria_anterior->category_id)->first();

                    // Obtener nueva categoría
                    $sub_categoria_nueva = SubCategoria::where('id', $request->id_catalogo ?? $producto->id_catalogo)->first();
                    $categoria_nueva = CatalogoCategoria::where('id', $sub_categoria_nueva->category_id)->first();
                    break;
                default:
                    break;
            }

            if ($archivo) {
                $nombreOriginal = $archivo->getClientOriginalName();
                $extension = $archivo->getClientOriginalExtension();
                $nombreSinExtension = pathinfo($nombreOriginal, PATHINFO_FILENAME);
                $nombreUnico = $nombreSinExtension . now()->format('Y-m-d_H_i_s') . '.' . $extension;

                $archivo->storeAs(
                    'fotos_producto',
                    $nombreUnico,
                    'private'
                );

                $disco = Storage::disk('ftp_brimagy');

                // Subir a la nueva categoría
                $disco->put(
                    $categoria_nueva->file_path . '/' . $nombreUnico,
                    file_get_contents($archivo->getRealPath())
                );

                $rutaAnterior = $categoria_anterior->file_path . '/' . $producto->foto_producto;
                if ($disco->exists($rutaAnterior)) {
                    $disco->delete($rutaAnterior);
                }

                // Mover fotos adicionales a la nueva categoría
                $fotosAdicionales = FotosProducto::where('id_producto', $request->id_producto)->get();

                foreach ($fotosAdicionales as $foto) {
                    $rutaFotoAnterior = $categoria_anterior->file_path . '/' . $foto->nombre;
                    $rutaFotoNueva = $categoria_nueva->file_path . '/' . $foto->nombre;

                    if ($disco->exists($rutaFotoAnterior)) {
                        $disco->put($rutaFotoNueva, $disco->get($rutaFotoAnterior));
                        $disco->delete($rutaFotoAnterior);
                    }

                    $foto->update([
                        'nombre' => $foto->nombre,
                    ]);

                    if ($foto->id_foto_brimagy) {
                        switch ($nombre_plataforma) {
                            case "club bohn":
                                FotosProductoClub::where('id', $foto->id_foto_brimagy)
                                    ->update(['photo' => $foto->nombre]);
                                break;
                            case "puntotes":
                                FotosProductoBrimagy::where('id', $foto->id_foto_brimagy)
                                    ->update(['photo' => $foto->nombre]);
                                break;
                            default:
                                break;
                        }
                    }
                }

                $datosParaActualizar['foto_producto'] = $nombreUnico;
            } elseif (
                $categoria_anterior &&
                $categoria_nueva &&
                $categoria_anterior->id !== $categoria_nueva->id &&
                !empty($nombreUnico)
            ) {
                $disco = Storage::disk('ftp_brimagy');
                $rutaAnterior = $categoria_anterior->file_path . '/' . $nombreUnico;
                $rutaNueva = $categoria_nueva->file_path . '/' . $nombreUnico;

                if ($disco->exists($rutaAnterior)) {
                    $disco->put($rutaNueva, $disco->get($rutaAnterior));
                    $disco->delete($rutaAnterior);
                }

                $datosParaActualizar['foto_producto'] = $nombreUnico;

                $fotosAdicionales = FotosProducto::where('id_producto', $request->id_producto)->get();

                foreach ($fotosAdicionales as $foto) {
                    $rutaFotoAnterior = $categoria_anterior->file_path . '/' . $foto->nombre;
                    $rutaFotoNueva = $categoria_nueva->file_path . '/' . $foto->nombre;

                    if ($disco->exists($rutaFotoAnterior)) {
                        $disco->put($rutaFotoNueva, $disco->get($rutaFotoAnterior));
                        $disco->delete($rutaFotoAnterior);
                    }

                    $foto->update([
                        'nombre' => $foto->nombre,
                    ]);
                    if ($foto->id_foto_brimagy) {
                        switch ($nombre_plataforma) {
                            case "club bohn":
                                FotosProductoClub::where('id', $foto->id_foto_brimagy)
                                    ->update(['photo' => $foto->nombre]);
                                break;
                            case "puntotes":
                                FotosProductoBrimagy::where('id', $foto->id_foto_brimagy)
                                    ->update(['photo' => $foto->nombre]);
                                break;
                            default:
                                break;
                        }
                    }
                }
            }

            $datosParaActualizar['fee_brimagy'] = $fee_brimagy;
            $datosParaActualizar['envio_base'] = $envio_base;
            $datosParaActualizar['costo_caja'] = $costo_caja;
            $datosParaActualizar['envio_extra'] = $envio_extra;

            $datosParaActualizar['costo_con_iva'] = $costo_proveedor_con_iva;
            $datosParaActualizar['costo_sin_iva'] = $costo_proveedor_sin_iva;
            $datosParaActualizar['costo_puntos_con_iva'] = $costo_puntos_con_iva;
            $datosParaActualizar['costo_puntos_sin_iva'] = $costo_puntos_sin_iva;
            $datosParaActualizar['subtotal'] = $subtotal;
            $datosParaActualizar['total_envio'] = $total_envio;
            $datosParaActualizar['total'] = $total;
            $datosParaActualizar['puntos'] = $redondeo;
            $datosParaActualizar['valor_factor'] = $valor_factor;
            $datosParaActualizar['factor'] = $puntos;
            $datosParaActualizar['updated_at'] = now()->setTimezone('America/Mexico_City');

            $producto->update($datosParaActualizar);

            switch ($nombre_plataforma) {
                case "club bohn":
                    $producto_brimagy = ProductoClub::find($request->id_producto_brimagy);
                    break;
                case "puntotes":
                    $producto_brimagy = ProductoBrimagy::find($request->id_producto_brimagy);
                    break;
                default:
                    break;
            }

            if (!$producto_brimagy) {
                DB::rollBack();
                return $this->sendError('No se encuentra el producto brimagy', 'error', 404);
            }

            $datosBrimagy = [
                'desc' => $request->nombre_producto,
                'required_score' => $puntos,
                'sub_category_id' => $request->id_catalogo,
                'photo_name' => $nombreUnico ?: $producto_brimagy->photo_name,
                'sku' => $request->sku,
                'features' => $request->descripcion,
                'TyC' => $request->tyc,
                'validity' => $request->vigencia,
            ];

            $producto_brimagy->update($datosBrimagy);

            // Detectar cambios para la bitácora
            $cambios = [];
            foreach ($camposAuditables as $campo) {
                $anterior = $valoresAnteriores[$campo] ?? null;
                $nuevo = $datosParaActualizar[$campo] ?? null;
                if ($nuevo !== null && round((float) $anterior, 2) !== round((float) $nuevo, 2)) {
                    $cambios[] = "{$campo}: {$anterior} → {$nuevo}";
                }
            }

            $descripcionCambios = !empty($cambios)
                ? implode(' | ', $cambios)
                : "Solo se editaron campos de texto";

            $userLog = Auth::user();
            BitacoraEventos::create([
                'evento' => 'Edición de producto',
                'tabla' => 'dc_catalogo_productos',
                'id_referencia' => $request->id_producto,
                'descripcion' => "Se editó la siguiente información del producto: {$descripcionCambios}",
                'id_usuario' => $userLog->id,
            ]);

            DB::commit();
            return $this->sendResponse("Se ha actualizado el producto con éxito");
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al actualizar el producto', $th, 500);
        }
    }
    public function eliminarProducto(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer|exists:dc_catalogo_productos,id'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id del producto.', $validator->errors());
            }

            $producto = CatalogoProductos::find($request->id_producto);

            if (!$producto) {
                DB::rollBack();
                return $this->sendError('Este producto no existe', [], 404);
            }

            // desactivar el producto de catalogo brimagy
            if ($request->plataforma === "club_bohn") {
                $producto_brimagy = ProductoClub::find($producto->id_producto_brimagy);
            } else {
                $producto_brimagy = ProductoBrimagy::find($producto->id_producto_brimagy);
            }

            if (!$producto_brimagy) {
                DB::rollBack();
                return $this->sendError('No se encuentra el producto en brimagy', 'error', 404);
            }
            $producto_brimagy->update([
                'status' => "INACTIVE",
            ]);
            // Eliminar el producto de catalogo dirac
            $producto->delete();

            $user = Auth::user();
            $log['evento'] = 'Eliminación de producto';
            $log['descripcion'] = "El usuario con id: {$user->id} eliminó un producto";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse('Producto eliminado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al eliminar el producto', $th->getMessage(), 500);
        }
    }

    public function marcarNoDisponible(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer|exists:dc_catalogo_productos,id'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id del producto.', $validator->errors());
            }

            $producto = CatalogoProductos::find($request->id_producto);

            if (!$producto) {
                DB::rollBack();
                return $this->sendError('Este producto no existe', [], 404);
            }

            if ($request->plataforma === 'club_bohn') {
                $producto_brimagy = ProductoClub::find($producto->id_producto_brimagy);
            } else {
                $producto_brimagy = ProductoBrimagy::find($producto->id_producto_brimagy);
            }

            if (!$producto_brimagy) {
                DB::rollBack();
                return $this->sendError('No se encuentra el producto en brimagy', 'error', 404);
            }
            $producto_brimagy->update([
                'stock' => 0,
            ]);
            $producto->update([
                'stock' => 0,
            ]);

            $user = Auth::user();
            $log['evento'] = 'Producto marcado como no disponible';
            $log['descripcion'] = "El usuario con id: {$user->id} marcó el producto {$producto->nombre_producto} como no disponible";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse('Producto eliminado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al eliminar el producto', $th->getMessage(), 500);
        }
    }

    public function marcarDisponible(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer|exists:dc_catalogo_productos,id'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id del producto.', $validator->errors());
            }

            $producto = CatalogoProductos::find($request->id_producto);

            if (!$producto) {
                DB::rollBack();
                return $this->sendError('Este producto no existe', [], 404);
            }

            if ($request->plataforma === 'club_bohn') {
                $producto_brimagy = ProductoClub::find($producto->id_producto_brimagy);
            } else {
                $producto_brimagy = ProductoBrimagy::find($producto->id_producto_brimagy);
            }

            if (!$producto_brimagy) {
                DB::rollBack();
                return $this->sendError('No se encuentra el producto en brimagy', 'error', 404);
            }
            $producto_brimagy->update([
                'stock' => 1000000,
            ]);
            $producto->update([
                'stock' => 1000000,
            ]);

            $user = Auth::user();
            $log['evento'] = 'Producto marcado como no disponible';
            $log['descripcion'] = "El usuario con id: {$user->id} marcó el producto {$producto->nombre_producto} como no disponible";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse('Producto eliminado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al eliminar el producto', $th->getMessage(), 500);
        }
    }

    public function busquedaInteligenteBrimagy(Request $request)
    {
        try {
            $esClubBohn = $request->plataforma === 'club_bohn';

            $query = DB::table('dc_catalogo_productos as cpt')
                ->select(
                    'cpt.id',
                    'cpt.nombre_producto',
                    'cpt.descripcion',
                    'cpt.marca',
                    'cpt.sku',
                    'cpt.color',
                    'cpt.talla',
                    'cpv.nombre as proveedor',
                    'cpt.id_catalogo',
                    'cpt.costo_con_iva',
                    'cpt.costo_sin_iva',
                    'cpt.costo_puntos_con_iva',
                    'cpt.costo_puntos_sin_iva',
                    'cpt.fee_brimagy',
                    'cpt.subtotal',
                    'cpt.envio_base',
                    'cpt.costo_caja',
                    'cpt.envio_extra',
                    'cpt.total_envio',
                    'cpt.total',
                    'cpt.puntos',
                    'cpt.valor_factor',
                    'cpt.factor',
                    'cpt.tipo_producto',
                    'p.nombre as nombre_plataforma',
                    'cpt.created_at as fecha_creacion',
                )
                ->leftJoin('dc_plataformas as p', 'cpt.id_plataforma', '=', 'p.id')
                ->leftJoin('dc_catalogo_proveedores as cpv', 'cpt.id_proveedor', '=', 'cpv.id')
                ->when(!$esClubBohn, function ($q) {
                    $q->leftJoin('sub_categories as sc', 'cpt.id_catalogo', '=', 'sc.id')
                        ->leftJoin('awards_categories as ac', 'sc.category_id', '=', 'ac.id')
                        ->addSelect('sc.desc as catalogo');
                });

            // PLATAFORMA
            $plataforma = $esClubBohn ? 'club bohn' : $request->plataforma;
            if (!empty($plataforma)) {
                $plataformaModel = Plataformas::where('nombre', $plataforma)->first();

                if (!$plataformaModel) {
                    return $this->sendError('La plataforma "' . $plataforma . '" no existe', 'error', 404);
                }

                $query->where('cpt.id_plataforma', $plataformaModel->id);
            }

            // TIPO DE PRODUCTO (fisico / digital / todos)
            if ($request->has('tipo_producto') && in_array($request->tipo_producto, ['fisico', 'digital'])) {
                $query->where('cpt.tipo_producto', $request->tipo_producto);
            }

            // BÚSQUEDA POR PUNTOS CON RANGO
            if ($request->has('puntos') && !empty($request->puntos)) {
                $puntos = (int) $request->puntos;
                $porcentaje = $puntos * .20;
                $rangoMinimo = $puntos - $porcentaje;
                $rangoMaximo = $puntos + $porcentaje;

                $query->whereBetween('cpt.factor', [$rangoMinimo, $rangoMaximo]);
            }

            // BÚSQUEDA POR CATEGORÍA
            if ($request->has('categoria') && !empty($request->categoria)) {
                $categoria = $request->categoria;

                if ($esClubBohn) {
                    $query->where('cpt.id_catalogo', '=', $categoria);
                } else {
                    $query->where('sc.id', '=', $categoria);
                }
            }

            $productos = $query->orderBy('cpt.created_at', 'desc')->get();

            if ($esClubBohn) {
                $idsCatalogo = $productos->pluck('id_catalogo')->filter()->unique()->values();

                $subCategorias = SubCategoriaClub::whereIn('id', $idsCatalogo)
                    ->pluck('desc', 'id');

                $productos = $productos->map(function ($producto) use ($subCategorias) {
                    $producto->catalogo = $subCategorias[$producto->id_catalogo] ?? null;
                    return $producto;
                });
            }

            return $this->sendResponse($productos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }
    public function getBitacoraProductoPorId(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer|exists:dc_catalogo_productos,id'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el id del producto.', $validator->errors());
            }

            $query = DB::table('dc_catalogo_productos as cdp')
                ->select(
                    'up.name as nombre_usuario',
                    'up.email as correo_usuario',
                    'be.evento',
                    'be.descripcion',
                    'be.created_at as fecha_edicion',
                )
                ->leftJoin('bitacora_eventos as be', 'cdp.id', '=', 'be.id_referencia')
                ->leftJoin('dc_usuarios_plataforma as up', 'be.id_usuario', '=', 'up.id')
                ->where('be.id_referencia', $request->id_producto);

            $productos = $query->orderBy('be.created_at', 'desc')->get();

            return $this->sendResponse($productos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }
    public function getCatalogoProductosDigitalesBrimagy(Request $request)
    { //funcion para obtener productos de la base de brimagy
        try {
            $query = DB::connection('mysql_brimagy')->table('awards_view as av')
                ->select(
                    'av.id',
                    'av.desc as nombre_producto',
                    'av.sku',
                    'av.category as catalogo',
                    'av.features as descripcion',
                    'av.required_score',
                    'av.created_at as fecha_creacion',
                )
                ->where('av.status', 'ACTIVE');

            $productos = $query->orderBy('av.created_at', 'desc')->get();

            return $this->sendResponse($productos);
            //return $this->sendResponse($prueba);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }
    public function getCatalogoProductosPuntotesAwards(Request $request)
    { //funcion para obtener productos de la base de brimagy
        try {
            $query = DB::connection('mysql_brimagy')->table('awards as av')
                ->select(
                    'av.id',
                    'av.desc as nombre_producto',
                    'av.sku',
                    'av.sub_category_id as catalogo',
                    'av.required_score',
                    'av.created_at as fecha_creacion',
                )
                ->where('av.status', 'ACTIVE');

            $productos = $query->orderBy('av.created_at', 'desc')->get();

            return $this->sendResponse($productos);
            //return $this->sendResponse($prueba);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }
    public function getCatalogoClubBohnBrimagy(Request $request)
    { //funcion para obtener productos de la base de CLUB BOHN
        try {
            $query = DB::connection('mysql_club_bohn')->table('awards_view as av')
                ->select(
                    'av.id',
                    'av.desc as nombre_producto',
                    'av.sku',
                    'av.category as catalogo',
                    'av.required_score as costo_puntos_sin_iva',
                    'av.created_at as fecha_creacion',
                )
                ->where('av.status', 'ACTIVE');

            $productos = $query->orderBy('av.created_at', 'desc')->get();

            return $this->sendResponse($productos);
            //return $this->sendResponse($prueba);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }
    //fotos del producto
    public function subirFotosProducto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_producto' => 'required',
            'id_producto_brimagy' => 'nullable',
            'fotos_producto' => 'required|array',
            'fotos_producto.*' => 'required|file|mimes:jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->file('fotos_producto') as $archivo) {
                $nombreOriginal = $archivo->getClientOriginalName();
                $extension = $archivo->getClientOriginalExtension();
                $nombreSinExtension = pathinfo($nombreOriginal, PATHINFO_FILENAME);
                $nombreUnico = $nombreSinExtension . now()->format('Y-m-d_H_i_s') . '.' . $extension;

                $producto = CatalogoProductos::where('id', $request->id_producto)->first();

                if ($request->plataforma === "club_bohn") {
                    $sub_categoria = SubCategoriaClub::where('id', $producto->id_catalogo)->first();
                    $categoria = CategoriaClub::where('id', $sub_categoria->category_id)->first();
                } else {
                    $sub_categoria = SubCategoria::where('id', $producto->id_catalogo)->first();
                    $categoria = CatalogoCategoria::where('id', $sub_categoria->category_id)->first();
                }

                $rutaImagenBrimagy = $categoria->file_path . '/' . $nombreUnico;

                $ruta = $archivo->storeAs(
                    'fotos_producto',
                    $nombreUnico,
                    'private'
                );

                $contenidoArchivo = file_get_contents($archivo->getRealPath());
                Storage::disk('ftp_brimagy')->put($rutaImagenBrimagy, $contenidoArchivo);

                if ($request->plataforma === 'club_bohn') {
                    $fotoDirac = FotosProductoClub::create([
                        'award_id' => $request->id_producto_brimagy,
                        'photo' => $nombreUnico,
                        'status' => "ACTIVE",
                    ]);
                } else {
                    $fotoDirac = FotosProductoBrimagy::create([
                        'award_id' => $request->id_producto_brimagy,
                        'photo' => $nombreUnico,
                        'status' => "ACTIVE",
                    ]);
                }

                FotosProducto::create([
                    'id_producto' => $request->id_producto,
                    'id_foto_brimagy' => $fotoDirac->id,
                    'nombre' => $nombreUnico,
                    'nombre_original' => $nombreOriginal,
                    'url_foto' => $ruta,

                ]);
            }

            $fotos = FotosProducto::where('id_producto', $request->id_producto)->get();

            return $this->sendResponse($fotos, 'Fotos subidas correctamente');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir las fotos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getProductoFotoPorId(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el id del producto.', $validator->errors());
            }

            $query = DB::table('dc_fotos_producto as fp')
                ->select(
                    'fp.id',
                    'fp.id_producto',
                    'fp.id_foto_brimagy',
                    'fp.nombre',
                    'fp.nombre_original',
                    'fp.url_foto',
                    'fp.status',
                )
                ->where('fp.id_producto', $request->id_producto);

            $fotos = $query->orderBy('fp.created_at', 'desc')->get();

            return $this->sendResponse($fotos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener las fotos', $th, 500);
        }
    }
    public function desactivarFotosProducto(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_foto' => 'required|integer',
                'id_foto_brimagy' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $foto = FotosProducto::find($request->id_foto);
            if ($request->plataforma === 'club_bohn') {
                $foto_brimagy = FotosProductoClub::find($request->id_foto_brimagy);
            } else {
                $foto_brimagy = FotosProductoBrimagy::find($request->id_foto_brimagy);
            }

            if (!$foto) {
                DB::rollBack();
                return $this->sendError('Esta foto no se encuentra', 'error', 404);
            }
            if (!$foto_brimagy) {
                DB::rollBack();
                return $this->sendError('Esta foto no se encuentra', 'error', 404);
            }

            $foto->update([
                'status' => "INACTIVE",
            ]);
            $foto_brimagy->update([
                'status' => "INACTIVE",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se desactivó una foto de producto',
                'descripcion' => "El usuario con id: {$user->id} desactivó la foto {$foto->nombre}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($foto, 'Foto desactivada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al desactivar la foto', $th->getMessage(), 500);
        }
    }
    public function activarFotosProducto(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_foto' => 'required|integer',
                'id_foto_brimagy' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $foto = FotosProducto::find($request->id_foto);
            if ($request->plataforma === 'club_bohn') {
                $foto_brimagy = FotosProductoClub::find($request->id_foto_brimagy);
            } else {
                $foto_brimagy = FotosProductoBrimagy::find($request->id_foto_brimagy);
            }

            if (!$foto) {
                DB::rollBack();
                return $this->sendError('Esta foto no se encuentra', 'error', 404);
            }
            if (!$foto_brimagy) {
                DB::rollBack();
                return $this->sendError('Esta foto no se encuentra', 'error', 404);
            }

            $foto->update([
                'status' => "ACTIVE",
            ]);
            $foto_brimagy->update([
                'status' => "ACTIVE",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se activó una foto de producto',
                'descripcion' => "El usuario con id: {$user->id} activó la foto {$foto->nombre}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($foto, 'Foto activada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al activar la foto', $th->getMessage(), 500);
        }
    }
    //fotos promo del producto
    public function subirFotosPromoProducto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_producto' => 'required',
            'id_producto_brimagy' => 'nullable',
            'fotos_promo_producto' => 'required|array',
            'fotos_promo_producto.*' => 'required|file|mimes:jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->file('fotos_promo_producto') as $archivo) {
                $nombreOriginal = $archivo->getClientOriginalName();
                $extension = $archivo->getClientOriginalExtension();
                $nombreSinExtension = pathinfo($nombreOriginal, PATHINFO_FILENAME);
                $nombreUnico = $nombreSinExtension . now()->format('Y-m-d_H_i_s') . '.' . $extension;

                $rutaPrueba = 'Ofertas/' . $nombreUnico;

                $ruta = $archivo->storeAs(
                    'fotos_promo_producto',
                    $nombreUnico,
                    'private'
                );

                $contenidoArchivo = file_get_contents($archivo->getRealPath());
                Storage::disk('ftp_brimagy')->put($rutaPrueba, $contenidoArchivo);

                if ($request->plataforma === 'club_bohn') {
                    $fotoDirac = FotosOfertasClub::create([
                        'award_id' => $request->id_producto_brimagy,
                        'offer' => $nombreUnico,
                        'status' => "ACTIVE",
                    ]);
                } else {
                    $fotoDirac = FotosOfertasBrimagy::create([
                        'award_id' => $request->id_producto_brimagy,
                        'offer' => $nombreUnico,
                        'status' => "ACTIVE",
                    ]);
                }

                FotosOfertas::create([
                    'id_producto' => $request->id_producto,
                    'id_foto_promo_brimagy' => $fotoDirac->id,
                    'nombre' => $nombreUnico,
                    'nombre_original' => $nombreOriginal,
                    'url_foto' => $ruta,

                ]);
            }

            $fotos = FotosOfertas::where('id_producto', $request->id_producto)->get();

            return $this->sendResponse($fotos, 'Fotos subidas correctamente');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir las fotos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getProductoFotoPromoPorId(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el id del producto.', $validator->errors());
            }

            $query = DB::table('dc_fotos_promo_producto as fpp')
                ->select(
                    'fpp.id',
                    'fpp.id_producto',
                    'fpp.id_foto_promo_brimagy',
                    'fpp.nombre',
                    'fpp.nombre_original',
                    'fpp.url_foto',
                    'fpp.status',
                )
                ->where('fpp.id_producto', $request->id_producto);

            $fotos = $query->orderBy('fpp.created_at', 'desc')->get();

            return $this->sendResponse($fotos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener las fotos', $th, 500);
        }
    }
    public function desactivarFotosPromoProducto(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_foto_promo' => 'required|integer',
                'id_foto_promo_brimagy' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $foto = FotosOfertas::find($request->id_foto_promo);

            if ($request->plataforma === 'club_bohn') {
                $foto_brimagy = FotosOfertasClub::find($request->id_foto_promo_brimagy);
            } else {
                $foto_brimagy = FotosOfertasBrimagy::find($request->id_foto_promo_brimagy);
            }

            if (!$foto) {
                DB::rollBack();
                return $this->sendError('Esta foto no se encuentra', 'error', 404);
            }
            if (!$foto_brimagy) {
                DB::rollBack();
                return $this->sendError('Esta foto no se encuentra', 'error', 404);
            }

            $foto->update([
                'status' => "INACTIVE",
            ]);
            $foto_brimagy->update([
                'status' => "INACTIVE",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se desactivó una foto de producto',
                'descripcion' => "El usuario con id: {$user->id} desactivó la foto {$foto->nombre}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($foto, 'Foto desactivada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al desactivar la foto', $th->getMessage(), 500);
        }
    }
    public function activarFotosPromoProducto(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_foto_promo' => 'required|integer',
                'id_foto_promo_brimagy' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $foto = FotosOfertas::find($request->id_foto_promo);

            if ($request->plataforma === 'club_bohn') {
                $foto_brimagy = FotosOfertasClub::find($request->id_foto_promo_brimagy);
            } else {
                $foto_brimagy = FotosOfertasBrimagy::find($request->id_foto_promo_brimagy);
            }

            if (!$foto) {
                DB::rollBack();
                return $this->sendError('Esta foto no se encuentra', 'error', 404);
            }
            if (!$foto_brimagy) {
                DB::rollBack();
                return $this->sendError('Esta foto no se encuentra', 'error', 404);
            }

            $foto->update([
                'status' => "ACTIVE",
            ]);
            $foto_brimagy->update([
                'status' => "ACTIVE",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se activó una foto de producto',
                'descripcion' => "El usuario con id: {$user->id} activó la foto {$foto->nombre}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($foto, 'Foto activada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al activar la foto', $th->getMessage(), 500);
        }
    }
    //montos digital
    public function crearEditarMontoProducto(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_producto_brimagy' => 'nullable|integer',
                'id_producto_dirac' => 'nullable|integer',
                'id_monto_brimagy' => 'nullable|integer',
                'id_monto' => 'nullable|integer',
                'monto' => 'required|string',
                'puntos' => 'required|integer',
                'descripcion' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $montoExistente = Montos::where('id', $request->id_monto)->first();

            if ($request->plataforma === 'club_bohn') {
                $montoExistenteBrimagy = MontosClub::where('id', $request->id_monto_brimagy)->first();
            } else {
                $montoExistenteBrimagy = MontosBrimagy::where('id', $request->id_monto_brimagy)->first();
            }

            if ($montoExistente) {

                $montoExistenteNombre = Montos::where('monto', $request->monto)
                    ->where('id_producto', $request->id_producto_dirac)
                    ->first();
                if ($montoExistenteNombre) {
                    DB::rollBack();
                    return $this->sendError('El monto ' . $request->monto . ' ya existe', 'error', 500);
                }

                $valoresAnteriores = [
                    'monto' => $montoExistente->monto,
                    'puntos' => $montoExistente->puntos,
                    'descripcion' => $montoExistente->descripcion,
                    'status' => $montoExistente->status,
                ];
                $valoresNuevos = [
                    'monto' => $request->monto,
                    'puntos' => $request->puntos,
                    'descripcion' => $request->descripcion,
                    'status' => $request->status,
                ];

                $montoExistente->update([
                    'monto' => $request->monto,
                    'puntos' => $request->puntos,
                    'descripcion' => $request->descripcion,
                    'status' => "ACTIVE",
                    'updated_at' => now()->setTimezone('America/Mexico_City'),
                ]);

                $montoExistenteBrimagy->update([
                    'monto' => $request->monto,
                    'points' => $request->puntos,
                    'descripcion' => $request->descripcion,
                    'status' => "ACTIVE",
                    'updated_at' => now()->setTimezone('America/Mexico_City'),
                ]);


                $user = Auth::user();
                $cambios = [];
                foreach ($valoresNuevos as $campo => $valorNuevo) {
                    $valorAnterior = $valoresAnteriores[$campo];
                    if ((string) $valorAnterior !== (string) $valorNuevo) {
                        $cambios[] = "{$campo}: {$valorAnterior} → {$valorNuevo}";
                    }
                }
                $descripcionCambios = !empty($cambios)
                    ? implode(' | ', $cambios)
                    : "Sin cambios";

                BitacoraEventos::create([
                    'evento' => 'Edición de producto',
                    'tabla' => 'dc_montos_digital',
                    'id_referencia' => $request->id_producto_dirac,
                    'descripcion' => "Se editó la siguiente información del producto: {$descripcionCambios}",
                    'id_usuario' => $user->id,
                ]);

                DB::commit();
                return $this->sendResponse($montoExistente, 'Monto editado exitosamente.');
            }

            $montoExistenteNombre = Montos::where('monto', $request->monto)
                ->where('id_producto', $request->id_producto_dirac)
                ->first();

            if ($montoExistenteNombre) {
                DB::rollBack();
                return $this->sendError('El monto ' . $request->monto . ' ya existe', 'error', 500);
            }

            if ($request->plataforma === 'club_bohn') {
                $montoBrimagy = MontosClub::create([
                    'award_id' => $request->id_producto_brimagy,
                    'monto' => $request->monto,
                    'points' => $request->puntos,
                    'descripcion' => $request->descripcion,
                    'status' => "ACTIVE",
                ]);
            } else {
                $montoBrimagy = MontosBrimagy::create([
                    'award_id' => $request->id_producto_brimagy,
                    'monto' => $request->monto,
                    'points' => $request->puntos,
                    'descripcion' => $request->descripcion,
                    'status' => "ACTIVE",
                ]);
            }

            $monto = Montos::create([
                'id_producto' => $request->id_producto_dirac,
                'id_monto_brimagy' => $montoBrimagy->id,
                'monto' => $request->monto,
                'puntos' => $request->puntos,
                'descripcion' => $request->descripcion,
                'status' => "ACTIVE",
            ]);

            $user = Auth::user();
            $log['evento'] = 'Creación de monto';
            $log['id_referencia'] = $request->id_producto_dirac;
            $log['descripcion'] = "El usuario con id: {$user->id} añadio el monto: {$request->monto} al producto {$request->id_producto_dirac}";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($monto, 'Monto registrado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar el monto', $th->getMessage(), 500);
        }
    }
    public function getProductoMontoPorId(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el id del monto.', $validator->errors());
            }

            $query = DB::table('dc_montos_digital as md')
                ->select(
                    'md.id',
                    'md.id_monto_brimagy',
                    'md.monto',
                    'md.puntos',
                    'md.descripcion',
                    'md.status',
                )
                ->where('md.id_producto', $request->id_producto);

            $montos = $query->orderBy('md.created_at', 'desc')->get();

            return $this->sendResponse($montos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los montos', $th, 500);
        }
    }
    public function desactivarMontoProducto(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_monto' => 'required|integer',
                'id_monto_brimagy' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $monto = Montos::find($request->id_monto);

            if ($request->plataforma === 'club_bohn') {
                $monto_brimagy = MontosClub::find($request->id_monto_brimagy);
            } else {
                $monto_brimagy = MontosBrimagy::find($request->id_monto_brimagy);
            }

            if (!$monto) {
                DB::rollBack();
                return $this->sendError('Este monto no se encuentra', 'error', 404);
            }
            if (!$monto_brimagy) {
                DB::rollBack();
                return $this->sendError('Este monto no se encuentra', 'error', 404);
            }

            $monto->update([
                'status' => "INACTIVE",
            ]);
            $monto_brimagy->update([
                'status' => "INACTIVE",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se desactivó un monto de producto',
                'descripcion' => "El usuario con id: {$user->id} desactivó el monto {$monto->monto}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($monto, 'Monto desactivado correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al desactivar el monto', $th->getMessage(), 500);
        }
    }
    public function activarMontoProducto(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_monto' => 'required|integer',
                'id_monto_brimagy' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $monto = Montos::find($request->id_monto);

            if ($request->plataforma === 'club_bohn') {
                $monto_brimagy = MontosClub::find($request->id_monto_brimagy);
            } else {
                $monto_brimagy = MontosBrimagy::find($request->id_monto_brimagy);
            }

            if (!$monto) {
                DB::rollBack();
                return $this->sendError('Este monto no se encuentra', 'error', 404);
            }
            if (!$monto_brimagy) {
                DB::rollBack();
                return $this->sendError('Este monto no se encuentra', 'error', 404);
            }

            $monto->update([
                'status' => "ACTIVE",
            ]);
            $monto_brimagy->update([
                'status' => "ACTIVE",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se activó un monto de producto',
                'descripcion' => "El usuario con id: {$user->id} activó el monto {$monto->monto}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($monto, 'Monto activado correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al activar el monto', $th->getMessage(), 500);
        }
    }
    //fotos de monto digital
    public function subirFotoMonto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_producto' => 'required',
            'id_producto_brimagy' => 'nullable',
            'foto_monto' => 'required|file|mimes:jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $fotoExistente = FotoMontos::where('id_producto', $request->id_producto)->first();
            $producto = CatalogoProductos::where('id', $request->id_producto)->first();

            $archivo = $request->file('foto_monto');
            $nombreOriginal = $archivo->getClientOriginalName();
            $extension = $archivo->getClientOriginalExtension();
            $nombreSinExt = pathinfo($nombreOriginal, PATHINFO_FILENAME);
            $nombreUnico = $producto->nombre_producto . '.' . $extension;

            if ($fotoExistente) {
                if (Storage::disk('private')->exists($fotoExistente->url_foto)) {
                    Storage::disk('private')->delete($fotoExistente->url_foto);
                }

                $rutaFtpAnterior = 'Gifs/detalles/' . $fotoExistente->nombre;
                if (Storage::disk('ftp_brimagy')->exists($rutaFtpAnterior)) {
                    Storage::disk('ftp_brimagy')->delete($rutaFtpAnterior);
                }
            }

            $ruta = $archivo->storeAs(
                'foto_monto/' . $request->id_producto,
                $nombreUnico,
                'private'
            );

            $rutaFtp = 'Gifs/detalles/' . $nombreUnico;
            $contenidoArchivo = file_get_contents($archivo->getRealPath());
            Storage::disk('ftp_brimagy')->put($rutaFtp, $contenidoArchivo);

            if ($fotoExistente) {
                $fotoExistente->update([
                    'nombre' => $nombreUnico,
                    'nombre_original' => $nombreOriginal,
                    'url_foto' => $ruta,
                ]);
                $foto = $fotoExistente->fresh();
            } else {
                $foto = FotoMontos::create([
                    'id_producto' => $request->id_producto,
                    'nombre' => $nombreUnico,
                    'nombre_original' => $nombreOriginal,
                    'url_foto' => $ruta,
                    'status' => "ACTIVE",
                ]);
            }

            $fotosMonto = FotoMontos::where('id_producto', $request->id_producto)->get();

            return $this->sendResponse($fotosMonto, 'Foto subida correctamente');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir las fotos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getFotoMontoPorId(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el id del producto.', $validator->errors());
            }

            $query = DB::table('dc_foto_montos as fm')
                ->select(
                    'fm.id',
                    'fm.id_producto',
                    'fm.nombre',
                    'fm.nombre_original',
                    'fm.url_foto',
                    'fm.status',
                )
                ->where('fm.id_producto', $request->id_producto);

            $fotos = $query->orderBy('fm.created_at', 'desc')->get();

            return $this->sendResponse($fotos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener la foto', $th, 500);
        }
    }
    public function desactivarFotoMonto(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_foto_monto' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $foto = FotoMontos::find($request->id_foto_monto);

            if (!$foto) {
                DB::rollBack();
                return $this->sendError('Esta foto no se encuentra', 'error', 404);
            }

            $foto->update([
                'status' => "INACTIVE",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se desactivó una foto de monto',
                'descripcion' => "El usuario con id: {$user->id} desactivó la foto {$foto->nombre}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($foto, 'Foto desactivada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al desactivar la foto', $th->getMessage(), 500);
        }
    }
    public function activarFotoMonto(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_foto_monto' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $foto = FotoMontos::find($request->id_foto_monto);

            if (!$foto) {
                DB::rollBack();
                return $this->sendError('Esta foto no se encuentra', 'error', 404);
            }

            $foto->update([
                'status' => "ACTIVE",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se activó una foto de monto',
                'descripcion' => "El usuario con id: {$user->id} activó la foto {$foto->nombre}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($foto, 'Foto activada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al activar la foto', $th->getMessage(), 500);
        }
    }
}
