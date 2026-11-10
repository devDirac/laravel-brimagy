<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Exceptions\RecursoNoEncontradoException;
use App\Http\Controllers\API\Concerns\RespondeConServicios;
use App\Services\Productos\DisponibilidadProductoService;
use App\Services\Productos\EliminarProductoService;
use App\Services\Productos\Recursos\ColorProducto;
use App\Services\Productos\Recursos\FotoMontoProducto;
use App\Services\Productos\Recursos\FotoMontoService;
use App\Services\Productos\Recursos\FotoProducto;
use App\Services\Productos\Recursos\FotoPromoProducto;
use App\Services\Productos\Recursos\FotosRecursoService;
use App\Services\Productos\Recursos\MontoProducto;
use App\Services\Productos\Recursos\RecursoProducto;
use App\Services\Productos\Recursos\RecursoProductoService;
use App\Services\Productos\Recursos\TallaProducto;
use App\Http\Requests\Productos\CrearProductoRequest;
use App\Http\Requests\Productos\EditarProductoRequest;
use App\Services\Productos\EditarProductoService;
use App\Services\Productos\RegistrarProductoService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\BitacoraEventos;
use App\Models\CatalogoProductos;
use App\Models\Plataformas;
use App\Models\ProductoBrimagy;
use App\Models\ProductoClub;
use App\Models\ProductoLocal;
use App\Models\SubCategoriaClub;
use App\Models\ValidacionCanje;
use Carbon\Carbon;

class ProductosController extends BaseController
{
    use RespondeConServicios;

    public function crearProducto(CrearProductoRequest $request, RegistrarProductoService $registrarProducto)
    {
        try {
            $resultado = $registrarProducto->registrar(
                $request->except('foto_producto'),
                $request->file('foto_producto'),
                (int) Auth::id()
            );

            $producto = $resultado['producto'];
            $producto->setAttribute('actualizado', $resultado['actualizado']);

            return $this->sendResponse(
                $producto,
                $resultado['actualizado'] ? 'Producto actualizado exitosamente.' : 'Producto registrado exitosamente.'
            );
        } catch (RecursoNoEncontradoException $e) {
            return $this->sendError($e->getMessage(), 'error', 404);
        } catch (\Throwable $th) {
            report($th);
            return $this->sendError('Error al registrar el producto', $th->getMessage(), 500);
        }
    }
    public function crearEditarColorProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->guardarRecurso($recursos, new ColorProducto(), $request);
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
    public function desactivarColorProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->desactivarRecurso($recursos, new ColorProducto(), $request);
    }
    public function activarColorProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->activarRecurso($recursos, new ColorProducto(), $request);
    }
    //tallas
    public function crearEditarTallaProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->guardarRecurso($recursos, new TallaProducto(), $request);
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
    public function desactivarTallaProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->desactivarRecurso($recursos, new TallaProducto(), $request);
    }
    public function activarTallaProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->activarRecurso($recursos, new TallaProducto(), $request);
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
                ProductoLocal::create([
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

            $idsBrimagy = $productos->pluck('id_producto_brimagy')->filter()->unique()->values();

            if ($idsBrimagy->isNotEmpty()) {
                $modeloExterno = $esClubBohn ? ProductoClub::class : ProductoLocal::class;

                $extras = $modeloExterno::whereIn('id', $idsBrimagy)
                    ->get(['id', 'TyC', 'validity'])
                    ->keyBy('id');

                $productos = $productos->map(function ($producto) use ($extras) {
                    $extra = $extras->get($producto->id_producto_brimagy);
                    $producto->tyc = $extra->TyC ?? null;
                    $producto->vigencia = $extra->validity ?? null;
                    return $producto;
                });
            }

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

    public function editarProducto(EditarProductoRequest $request, EditarProductoService $editarProducto)
    {
        try {
            $huboCambios = $editarProducto->editar(
                $request->except('foto_producto'),
                $request->file('foto_producto'),
                (int) Auth::id()
            );

            if (!$huboCambios) {
                return $this->sendResponse("No hay cambios que guardar.");
            }

            return $this->sendResponse("Se ha actualizado el producto con éxito");
        } catch (RecursoNoEncontradoException $e) {
            return $this->sendError($e->getMessage(), 'error', 404);
        } catch (\Throwable $th) {
            report($th);
            return $this->sendError('Error al actualizar el producto', $th->getMessage(), 500);
        }
    }
    public function eliminarProducto(Request $request, EliminarProductoService $eliminarProducto)
    {
        return $this->responder(function () use ($request, $eliminarProducto) {
            $eliminarProducto->eliminar($request->all(), (int) Auth::id());

            return $this->sendResponse('Producto eliminado exitosamente.');
        }, 'Error al eliminar el producto');
    }

    public function marcarNoDisponible(Request $request, DisponibilidadProductoService $disponibilidad)
    {
        return $this->responder(function () use ($request, $disponibilidad) {
            $producto = $disponibilidad->marcar($request->all(), false, (int) Auth::id());

            return $this->sendResponse($producto, 'Producto marcado como no disponible.');
        }, 'Error al marcar el producto como no disponible');
    }

    public function marcarDisponible(Request $request, DisponibilidadProductoService $disponibilidad)
    {
        return $this->responder(function () use ($request, $disponibilidad) {
            $producto = $disponibilidad->marcar($request->all(), true, (int) Auth::id());

            return $this->sendResponse($producto, 'Producto marcado como disponible.');
        }, 'Error al marcar el producto como disponible');
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
    public function subirFotosProducto(Request $request, FotosRecursoService $fotos)
    {
        return $this->subirFotosRecurso($fotos, new FotoProducto(), $request);
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
    public function desactivarFotosProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->desactivarRecurso($recursos, new FotoProducto(), $request);
    }
    public function activarFotosProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->activarRecurso($recursos, new FotoProducto(), $request);
    }
    //fotos promo del producto
    public function subirFotosPromoProducto(Request $request, FotosRecursoService $fotos)
    {
        return $this->subirFotosRecurso($fotos, new FotoPromoProducto(), $request);
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
    public function desactivarFotosPromoProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->desactivarRecurso($recursos, new FotoPromoProducto(), $request);
    }
    public function activarFotosPromoProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->activarRecurso($recursos, new FotoPromoProducto(), $request);
    }
    //montos digital
    public function crearEditarMontoProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->guardarRecurso($recursos, new MontoProducto(), $request);
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
    public function desactivarMontoProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->desactivarRecurso($recursos, new MontoProducto(), $request);
    }
    public function activarMontoProducto(Request $request, RecursoProductoService $recursos)
    {
        return $this->activarRecurso($recursos, new MontoProducto(), $request);
    }
    //fotos de monto digital
    public function subirFotoMonto(Request $request, FotoMontoService $fotoMonto)
    {
        return $this->responder(function () use ($request, $fotoMonto) {
            return $this->sendResponse($fotoMonto->subir($request->all()), 'Foto subida correctamente');
        }, 'Error al subir las fotos');
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
    public function desactivarFotoMonto(Request $request, RecursoProductoService $recursos)
    {
        return $this->desactivarRecurso($recursos, new FotoMontoProducto(), $request);
    }
    public function activarFotoMonto(Request $request, RecursoProductoService $recursos)
    {
        return $this->activarRecurso($recursos, new FotoMontoProducto(), $request);
    }

    // ---------- Recursos del producto (colores, tallas, montos, fotos) ----------

    /** Alta o edición de un color, talla o monto. */
    private function guardarRecurso(RecursoProductoService $recursos, RecursoProducto $recurso, Request $request)
    {
        return $this->responder(function () use ($recursos, $recurso, $request) {
            $resultado = $recursos->guardar($recurso, $request->all(), (int) Auth::id());

            return $this->sendResponse($resultado['registro'], $recurso->mensajeGuardado($resultado['editado']));
        }, $recurso->mensajeError('registrar'));
    }

    private function activarRecurso(RecursoProductoService $recursos, RecursoProducto $recurso, Request $request)
    {
        return $this->responder(function () use ($recursos, $recurso, $request) {
            $registro = $recursos->cambiarEstatus($recurso, $request->all(), true, (int) Auth::id());

            return $this->sendResponse($registro, $recurso->mensajeEstatus(true));
        }, $recurso->mensajeError('activar'));
    }

    private function desactivarRecurso(RecursoProductoService $recursos, RecursoProducto $recurso, Request $request)
    {
        return $this->responder(function () use ($recursos, $recurso, $request) {
            $registro = $recursos->cambiarEstatus($recurso, $request->all(), false, (int) Auth::id());

            return $this->sendResponse($registro, $recurso->mensajeEstatus(false));
        }, $recurso->mensajeError('desactivar'));
    }

    private function subirFotosRecurso(FotosRecursoService $fotos, RecursoProducto $recurso, Request $request)
    {
        return $this->responder(function () use ($fotos, $recurso, $request) {
            return $this->sendResponse($fotos->subir($recurso, $request->all()), 'Fotos subidas correctamente');
        }, 'Error al subir las fotos');
    }
}
