<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Mail\EnvioCotizacion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\BitacoraEventos;
use App\Models\CatalogoProveedores;
use App\Models\Facturas;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraFiles;
use App\Models\ValidacionCanje;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OrdenCompraController extends BaseController
{
    protected $whatsappService;

    public function __construct()
    {
        $this->whatsappService = new WhatsAppService();
    }

    public function crearOrdenCompra(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_canje' => 'required|integer',
                'id_usuario' => 'required|integer|exists:users,id',
                'id_proveedor' => 'required|integer|exists:dc_catalogo_proveedores,id'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $ordenCompra = OrdenCompra::create([
                'id_usuario' => $request->id_usuario,
                'no_orden' => $request->no_orden,
                'id_articulo' => $request->id_articulo,
                'id_proveedor' => $request->id_proveedor,
                'observaciones' => $request->observaciones,
                'estatus' => "orden_generada",
            ]);

            $user = Auth::user();
            $log['evento'] = 'Creación de orden de compra';
            $log['descripcion'] = "El usuario con id: {$user->id} generó la orden de compra {$ordenCompra->id}";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($ordenCompra, 'Orden de compra generada exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al generar la orden de compra', $th->getMessage(), 500);
        }
    }

    public function getProveedoresOC(Request $request)
    {
        try {
            // Obtener todos los IDs de canjes que están en órdenes de compra
            $productosEnOrdenes = DB::table('dc_orden_compra')
                ->select('productos_canje')
                ->get()
                ->map(function ($producto) {
                    $productos = json_decode($producto->productos_canje, true);
                    return is_array($productos) ? array_keys($productos) : [];
                })
                ->flatten()
                ->unique()
                ->filter()
                ->toArray();

            $query = DB::table('dc_catalogo_proveedores as cp')
                ->select(
                    'cp.id',
                    'cp.nombre',
                    'cp.razon_social',
                    'cp.descripcion',
                    'cp.nombre_contacto',
                    'cp.telefono',
                    'cp.correo',
                    DB::raw('(SELECT COUNT(cpro.id) 
                          FROM dc_catalogo_productos cpro
                          JOIN dc_validacion_canje vc ON cpro.id = vc.id_producto
                          WHERE cpro.id_proveedor = cp.id
                          AND vc.estatus = "identidad_validada"
                          ' . (!empty($productosEnOrdenes) ? 'AND cpro.id NOT IN (' . implode(',', $productosEnOrdenes) . ')' : '') . '
                         ) as total_canjes'),
                    DB::raw('(SELECT COUNT(oc2.id)
                          FROM dc_orden_compra oc2
                          WHERE oc2.id_proveedor = cp.id
                         ) as total_ordenes_compra')
                );

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('cp.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('cp.correo', 'LIKE', "%{$search}%");
                });
            }

            $proveedores = $query
                ->havingRaw('total_canjes > 0 OR total_ordenes_compra > 0')
                ->orderByRaw('total_canjes DESC')
                ->get();

            return $this->sendResponse($proveedores);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los proveedores', $th, 500);
        }
    }

    public function getOCPorId(Request $request)
    {
        try {
            // Obtener la orden de compra con los datos del proveedor
            $ordenCompra = DB::table('dc_orden_compra as oc')
                ->select(
                    'oc.id',
                    'oc.no_orden',
                    'oc.id_usuario',
                    'oc.id_proveedor',
                    'oc.productos_canje',
                    'oc.observaciones',
                    'oc.estatus',
                    'oc.created_at',
                    'oc.updated_at',
                    'cp.nombre as nombre_proveedor',
                    'cp.razon_social',
                    'cp.descripcion as descripcion_proveedor',
                    'cp.nombre_contacto',
                    'cp.telefono',
                    'u.id as id_usuario',
                    'u.name as nombre_vendedor',
                    'u.first_last_name as primer_apellido',
                    'u.second_last_name as segundo_apellido',
                    'cp.correo'
                )
                ->leftJoin('dc_catalogo_proveedores as cp', 'oc.id_proveedor', '=', 'cp.id')
                ->leftJoin('users as u', 'oc.id_usuario', '=', 'u.id')
                ->where('oc.id', $request->id_orden_compra)
                ->orderBy('oc.created_at', 'desc')
                ->first();

            if (!$ordenCompra) {
                return $this->sendError('No se encontró la orden de compra', null, 404);
            }

            // Decodificar productos
            $productosOrden = json_decode($ordenCompra->productos_canje, true);

            if (!$productosOrden || !is_array($productosOrden)) {
                return $this->sendError('No se encontraron productos en la orden', null, 404);
            }

            // Extraer los IDs de los canjes desde las claves del objeto
            $productosIds = array_keys($productosOrden);

            // Obtener los datos completos de los canjes desde swaps_view
            $canjesData = DB::table('dc_catalogo_productos as cdp')
                ->select(
                    'cdp.id',
                    'cdp.sku',
                    'cdp.created_at as creacion_canje',
                    'vc.estatus as estado_validacion',
                    'vc.fecha_validacion',
                    'vc.cantidad_producto as number_of_awards',
                    'cdp.nombre_producto as nombre_premio',
                    'cdp.marca',
                    'cdp.fee_brimagy',
                    'cdp.costo_sin_iva',
                    'cdp.costo_con_iva',
                )
                ->leftJoin('dc_validacion_canje as vc', 'cdp.id', '=', 'vc.id_producto')
                ->whereIn('cdp.id', $productosIds)
                ->get()
                ->keyBy('id');

            // Combinar los datos del JSON con los datos de la BD
            $productosCombinados = [];

            foreach ($productosOrden as $idCanje => $productoOrden) {
                $canjeData = $canjesData->get($idCanje);

                if (!$canjeData) {
                    continue;
                }

                // Combinar datos del canje con datos del producto de la orden
                $productosCombinados[] = array_merge(
                    (array) $canjeData,
                    $productoOrden
                );
            }

            $productosCombinados = collect($productosCombinados);

            // Preparar respuesta
            $respuesta = [
                'orden_compra' => [
                    'id' => $ordenCompra->id,
                    'no_orden' => $ordenCompra->no_orden,
                    'estatus' => $ordenCompra->estatus,
                    'observaciones' => $ordenCompra->observaciones,
                    'created_at' => $ordenCompra->created_at,
                    'updated_at' => $ordenCompra->updated_at,
                    'id_usuario' => $ordenCompra->id_usuario,
                    'nombre_vendedor' => $ordenCompra->nombre_vendedor,
                    'primer_apellido' => $ordenCompra->primer_apellido,
                    'segundo_apellido' => $ordenCompra->segundo_apellido,
                ],
                'proveedor' => [
                    'id' => $ordenCompra->id_proveedor,
                    'nombre' => $ordenCompra->nombre_proveedor,
                    'razon_social' => $ordenCompra->razon_social,
                    'descripcion' => $ordenCompra->descripcion_proveedor,
                    'nombre_contacto' => $ordenCompra->nombre_contacto,
                    'telefono' => $ordenCompra->telefono,
                    'correo' => $ordenCompra->correo,
                ],
                'productos' => $productosCombinados,
                'totales' => [
                    'subtotal' => $productosCombinados->sum('subtotal'),
                    'iva' => $productosCombinados->sum('iva'),
                    'total' => $productosCombinados->sum('importe_total'),
                ],
                'estadisticas' => [
                    'total_productos' => $productosCombinados->count(),
                    'productos_aceptados' => $productosCombinados->where('estatus_proveedor', 1)->count(),
                    'productos_rechazados' => $productosCombinados->where('estatus_proveedor', 2)->count(),
                    'productos_pendientes' => $productosCombinados->where('estatus_proveedor', 0)->count(),
                ]
            ];

            return $this->sendResponse($respuesta);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener la orden de compra', $th->getMessage(), 500);
        }
    }

    public function getOCPorIdProveedor(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_proveedor' => 'required|integer|exists:dc_catalogo_proveedores,id'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $query = DB::table('dc_orden_compra as oc')
                ->select(
                    'oc.id',
                    'oc.no_orden',
                    'oc.estatus as estado_orden',
                    'oc.created_at as fecha_creacion',
                    DB::raw('JSON_LENGTH(oc.productos_canje) as total_productos')
                )
                ->where('oc.id_proveedor', $request->id_proveedor);

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('oc.no_orden', 'LIKE', "%{$search}%")
                        ->orWhere('oc.estatus', 'LIKE', "%{$search}%");
                });
            }

            $ordenes_compra = $query->orderBy('oc.created_at', 'desc')->get();

            return $this->sendResponse($ordenes_compra);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al obtener las ordenes de compra', $th, 500);
        }
    }

    public function getCanjesPorProveedor(Request $request)
    {
        try {
            $query = DB::table('dc_catalogo_productos as cdp')
                ->select(
                    'cdp.id',
                    'vc.id as id_validacion_producto',
                    'vc.id_canje',
                    'cdp.sku',
                    'cdp.created_at as creacion_producto',
                    'vc.estatus as estado_validacion',
                    'vc.fecha_validacion',
                    'vc.cantidad_producto as number_of_awards',
                    'cdp.id as id_producto',
                    'cdp.nombre_producto as nombre_premio',
                    'cdp.marca',
                    'cp.nombre as nombre_proveedor',
                    'cdp.fee_brimagy',
                    'cp.razon_social',
                    'cdp.costo_sin_iva',
                )
                ->leftJoin('dc_catalogo_proveedores as cp', 'cdp.id_proveedor', '=', 'cp.id')
                ->leftJoin('dc_validacion_canje as vc', 'cdp.id', '=', 'vc.id_producto')
                ->where('cp.id', $request->id_proveedor)
                ->where('vc.estatus', '=', 'identidad_validada');

            $canjes = $query->orderBy('cdp.created_at', 'desc')->get();

            // Obtener todas las órdenes de compra del proveedor
            $ordenesCompra = DB::table('dc_orden_compra')
                ->where('id_proveedor', $request->id_proveedor)
                ->get();

            // Agregar el estatus_proveedor a cada canje
            $canjes = $canjes->map(function ($producto) use ($ordenesCompra) {
                $estatusProveedor = null;

                // Buscar en todas las órdenes de compra
                foreach ($ordenesCompra as $orden) {
                    $productosCanje = json_decode($orden->productos_canje, true);

                    if ($productosCanje && is_array($productosCanje)) {
                        // Buscar el canje actual en el array de productos
                        if (isset($productosCanje[$producto->id])) {
                            $estatusProveedor = $productosCanje[$producto->id]['estatus_proveedor'] ?? null;
                            break;
                        }
                    }
                }
                // Agregar el estatus al objeto canje
                $producto->estatus_proveedor = $estatusProveedor;

                return $producto;
            })
                ->filter(function ($canje) {
                    // Filtrar solo los canjes con estatus_proveedor null
                    return $canje->estatus_proveedor === null;
                })
                ->values();

            $response = [
                'productos' => $canjes
            ];

            return $this->sendResponse($response);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los canjes', $th, 500);
        }
    }

    public function enviarCotizacionProveedor(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_usuario' => 'required|integer|exists:users,id',
                'id_proveedor' => 'required|integer|exists:dc_catalogo_proveedores,id',
                'productos' => 'required|array',
                'productos.*.id_canje' => 'required|integer',
                'productos.*.id_producto' => 'required|integer',
                'productos.*.cantidad_producto' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $productosIds = collect($request->productos)->pluck('id_producto')->toArray();

            $productosData = DB::table('dc_catalogo_productos as cdp')
                ->select(
                    'cdp.id',
                    'cdp.nombre_producto as nombre_premio',
                    'cdp.sku',
                    'cdp.nombre_producto',
                    'cdp.marca',
                    'cdp.fee_brimagy',
                    'cdp.costo_sin_iva',
                    'cdp.costo_con_iva',
                    'cp.nombre as nombre_proveedor',
                    'cp.razon_social',
                    'vc.cantidad_producto',
                    'cp.telefono as telefono_proveedor',
                    'cp.correo as correo_proveedor'
                )
                ->leftJoin('dc_catalogo_proveedores as cp', 'cdp.id_proveedor', '=', 'cp.id')
                ->leftJoin('dc_validacion_canje as vc', 'cdp.id', '=', 'vc.id_producto')
                ->whereIn('cdp.id', $productosIds)
                ->get()
                ->keyBy('id');

            $productosCompletos = [];

            foreach ($request->productos as $productoRequest) {
                // Buscar el producto en la colección indexada
                $producto = $productosData->get($productoRequest['id_producto']);

                if (!$producto) {
                    continue; // Si no existe el producto, saltar
                }

                $precioUnitario = $producto->costo_sin_iva ?? 0;
                $cantidad = $productoRequest['cantidad_producto'] ?? 0;
                $porcentajeDescuento = $producto->fee_brimagy ?? 0;

                $subtotalSinDescuento = $precioUnitario * $cantidad;
                $descuentoEnPesos = $subtotalSinDescuento * ($porcentajeDescuento / 100);
                $subtotalConDescuento = $subtotalSinDescuento - $descuentoEnPesos;
                $iva = $subtotalConDescuento * 0.16;
                $importeTotal = $subtotalConDescuento + $iva;

                // Usar id_producto como clave del array asociativo
                $productosCompletos[$productoRequest['id_producto']] = [
                    'id_canje' => $productoRequest['id_canje'],
                    'nombre_producto' => $producto->nombre_premio,
                    'sku' => $producto->sku,
                    'cantidad_producto' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'porcentaje_descuento' => $porcentajeDescuento,
                    'subtotal' => $subtotalConDescuento,
                    'iva' => $iva,
                    'importe_total' => $importeTotal,
                    'estatus_proveedor' => $productoRequest['estatus_proveedor'] ?? 0,
                    'estatus_almacen' => $productoRequest['estatus_almacen'] ?? 0,
                    'tipo_compra' => $productoRequest['tipo_compra'] ?? '',
                ];
            }

            /*return response()->json([
                'datos' => json_encode($productosCompletos, JSON_FORCE_OBJECT)
            ]);*/

            $proveedor = $request->id_proveedor;
            $usuario = $request->id_usuario;
            $estado = "cotizacion_enviada_a_proveedor";

            $numeroOrden = $this->generarNumeroOrdenUnico();

            $cotizacion = OrdenCompra::create([
                'id_usuario' => $usuario,
                'id_proveedor' => $proveedor,
                'no_orden' => $numeroOrden,
                'productos_canje' => json_encode($productosCompletos, JSON_FORCE_OBJECT),
                'observaciones' => $request->observaciones,
                'estatus' => $estado,
            ]);

            $canjeIds = collect($request->productos)->pluck('id_canje')->toArray();

            ValidacionCanje::whereIn('id_canje', $canjeIds)
                ->update([
                    'no_orden' => $numeroOrden,
                ]);

            if ($cotizacion) {
                $productosArray = array_values($productosCompletos);
                $this->enviarWhatsApp($productosArray, $proveedor, $usuario, $estado, $cotizacion->no_orden);
                $this->enviarCorreo($productosArray, $proveedor, $usuario, $estado, $cotizacion->no_orden);
            }

            $user = Auth::user();
            $log['evento'] = 'Envío de cotización para proveedor';
            $log['descripcion'] = "El usuario con id: {$user->id} envió una cotización al proveedor {$proveedor}";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($cotizacion, 'Cotización enviada exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();

            return $this->sendError('Error al enviar la cotización', $th->getMessage(), 500);
        }
    }

    private function generarNumeroOrdenUnico()
    {
        $maxIntentos = 100;
        $intento = 0;

        do {
            // Generar 6 dígitos aleatorios
            $numeroAleatorio = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $numeroOrden = 'OCB-' . $numeroAleatorio;

            // Verificar si ya existe en la base de datos
            $existe = OrdenCompra::where('no_orden', $numeroOrden)->exists();

            $intento++;

            if ($intento >= $maxIntentos) {
                return $this->sendError('No se pudo generar un número de orden único después de ' . $maxIntentos . ' intentos', null, 500);
            }
        } while ($existe);

        return $numeroOrden;
    }
    private function encriptarCorto($id)
    {
        $key = env('APP_KEY');
        $encoded = base64_encode($id . '|' . time());
        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }
    private function desencriptarCorto($hash)
    {
        $decoded = base64_decode(strtr($hash, '-_', '+/'));
        [$id, $timestamp] = explode('|', $decoded);

        return $id;
    }
    private function enviarWhatsApp($canje, $proveedor = null, $usuario = null, $estado = null, $no_orden = null)
    {
        try {
            $telefonoProveedor = CatalogoProveedores::where('id', $proveedor)
                ->whereNotNull('telefono')
                ->where('telefono', '!=', '')
                ->value('telefono');

            $canjeIdEncriptado = $this->encriptarCorto($no_orden);
            $urlva = env('APP_FRONT_URL') . "/validar-ordencompra/{$canjeIdEncriptado}";

            if ($estado === "cotizacion_enviada_a_proveedor") {

                $mensaje = "Se le ha enviado una cotización de productos para su validación:\n\n";
                $mensaje .= "📦 *Productos solicitados:*\n";

                $totalGeneral = 0;
                foreach ($canje as $producto) {
                    $mensaje .= "• {$producto['nombre_producto']}\n";
                    $mensaje .= "  Cantidad: {$producto['cantidad_producto']}\n";
                    $mensaje .= "  Precio: $" . number_format($producto['precio_unitario'], 2) . "\n";
                    $mensaje .= "  Total: $" . number_format($producto['importe_total'], 2) . "\n\n";
                    $totalGeneral += $producto['importe_total'];
                }

                $mensaje .= "💰 *Total General: $" . number_format($totalGeneral, 2) . "*\n\n";
                $mensaje .= "🔗 Link para revisar y confirmar:\n{$urlva}\n\n";

                //$this->whatsappService->sendMessage($telefonoProveedor, $titulo);
                $this->whatsappService->sendMessage($telefonoProveedor, $mensaje);
            }
            return $this->sendResponse('Cotización enviada exitosamente.');
        } catch (\Throwable $th) {
            return $this->sendError('Error al enviar la cotización', $th->getMessage(), 500);
        }
    }

    private function enviarCorreo($canje, $proveedor = null, $usuario = null, $estado = null, $no_orden = null)
    {
        try {
            $correoProveedor = CatalogoProveedores::where('id', $proveedor)
                ->whereNotNull('correo')
                ->where('correo', '!=', '')
                ->value('correo');

            $totalGeneral = collect($canje)->sum('importe_total');
            $totalIva = collect($canje)->sum('iva');
            $ordenIdEncriptado = $this->encriptarCorto($no_orden);
            $urlva = env('APP_FRONT_URL') . "/validar-ordencompra/{$ordenIdEncriptado}";

            $productosData = (object)[
                'productos' => $canje,
                'total_general' => $totalGeneral,
                'total_iva' => $totalIva,
                'url_validacion' => $urlva,
            ];

            if ($estado === "cotizacion_enviada_a_proveedor") {
                Mail::to($correoProveedor)->send(new EnvioCotizacion($productosData, $urlva));
            }
            Log::info("Correo enviado correctamente");
        } catch (\Exception $e) {
            Log::error("Error al enviar correo: " . $e->getMessage());
        }
    }
    public function getOrdenCompraPorProveedor(Request $request)
    {
        try {
            $idOrdenCompraDesencriptado = $this->desencriptarCorto($request->id_ordencompra);

            // Obtener la orden de compra con los datos del proveedor
            $ordenCompra = DB::table('dc_orden_compra as oc')
                ->select(
                    'oc.id',
                    'oc.no_orden',
                    'oc.id_usuario',
                    'oc.id_proveedor',
                    'oc.productos_canje',
                    'oc.observaciones',
                    'oc.estatus',
                    'oc.created_at',
                    'oc.updated_at',
                    'cp.nombre as nombre_proveedor',
                    'cp.razon_social',
                    'cp.descripcion as descripcion_proveedor',
                    'cp.nombre_contacto',
                    'cp.telefono',
                    'u.id as id_usuario',
                    'u.name as nombre_vendedor',
                    'u.first_last_name as primer_apellido',
                    'u.second_last_name as segundo_apellido',
                    'cp.correo'
                )
                ->leftJoin('dc_catalogo_proveedores as cp', 'oc.id_proveedor', '=', 'cp.id')
                ->leftJoin('users as u', 'oc.id_usuario', '=', 'u.id')
                ->where('oc.no_orden', $idOrdenCompraDesencriptado)
                ->orderBy('oc.created_at', 'desc')
                ->first();

            if (!$ordenCompra) {
                return $this->sendError('No se encontró la orden de compra', null, 404);
            }

            // Decodificar productos
            $productosOrden = json_decode($ordenCompra->productos_canje, true);

            if (!$productosOrden || !is_array($productosOrden)) {
                return $this->sendError('No se encontraron productos en la orden', null, 404);
            }

            // Extraer los IDs de los canjes desde las claves del objeto
            $productosIds = array_keys($productosOrden);

            // Obtener los datos completos de los productos desde dc_catalogo_productos
            $canjesData = DB::table('dc_catalogo_productos as cdp')
                ->select(
                    'cdp.id',
                    'cdp.sku',
                    'cdp.created_at as creacion_canje',
                    'vc.estatus as estado_validacion',
                    'vc.fecha_validacion',
                    'cdp.nombre_producto as nombre_premio',
                    'cdp.marca',
                    'cdp.fee_brimagy',
                    'cdp.costo_sin_iva',
                    'cdp.costo_con_iva',
                )
                ->leftJoin('dc_validacion_canje as vc', 'cdp.id', '=', 'vc.id_producto')
                ->whereIn('cdp.id', $productosIds)
                ->get()
                ->keyBy('id'); // Indexar por ID

            // Combinar los datos del JSON con los datos de la BD
            $productosCombinados = [];

            foreach ($productosOrden as $idCanje => $productoOrden) {
                $canjeData = $canjesData->get($idCanje);

                if (!$canjeData) {
                    continue;
                }

                // Combinar datos del canje con datos del producto de la orden
                $productosCombinados[] = array_merge(
                    (array) $canjeData,
                    $productoOrden
                );
            }

            $productosCombinados = collect($productosCombinados);

            // Preparar respuesta
            $respuesta = [
                'orden_compra' => [
                    'id' => $ordenCompra->id,
                    'no_orden' => $ordenCompra->no_orden,
                    'estatus' => $ordenCompra->estatus,
                    'observaciones' => $ordenCompra->observaciones,
                    'created_at' => $ordenCompra->created_at,
                    'updated_at' => $ordenCompra->updated_at,
                    'id_usuario' => $ordenCompra->id_usuario,
                    'nombre_vendedor' => $ordenCompra->nombre_vendedor,
                    'primer_apellido' => $ordenCompra->primer_apellido,
                    'segundo_apellido' => $ordenCompra->segundo_apellido,
                ],
                'proveedor' => [
                    'id' => $ordenCompra->id_proveedor,
                    'nombre' => $ordenCompra->nombre_proveedor,
                    'razon_social' => $ordenCompra->razon_social,
                    'descripcion' => $ordenCompra->descripcion_proveedor,
                    'nombre_contacto' => $ordenCompra->nombre_contacto,
                    'telefono' => $ordenCompra->telefono,
                    'correo' => $ordenCompra->correo,
                ],
                'productos' => $productosCombinados,
                'totales' => [
                    'subtotal' => $productosCombinados->where('estatus_proveedor', 1)->sum('subtotal'),
                    'iva' => $productosCombinados->where('estatus_proveedor', 1)->sum('iva'),
                    'total' => $productosCombinados->where('estatus_proveedor', 1)->sum('importe_total'),
                ],
                'estadisticas' => [
                    'total_productos' => $productosCombinados->count(),
                    'productos_aceptados' => $productosCombinados->where('estatus_proveedor', 1)->count(),
                    'productos_rechazados' => $productosCombinados->where('estatus_proveedor', 2)->count(),
                    'productos_pendientes' => $productosCombinados->where('estatus_proveedor', 0)->count(),
                ]
            ];

            return $this->sendResponse($respuesta);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener la orden de compra', $th->getMessage(), 500);
        }
    }
    public function aceptarProductoOC(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $idProducto = $request->id_producto;

            // Buscar la orden que contiene este id_producto usando JSON_CONTAINS
            $ordenCompra = OrdenCompra::whereRaw(
                "JSON_CONTAINS_PATH(productos_canje, 'one', ?)",
                ['$."' . $idProducto . '"']
            )->first();

            if (!$ordenCompra) {
                DB::rollBack();
                return $this->sendError('No se encontró una orden de compra con este producto.', null, 404);
            }

            // Decodificar el JSON de productos (ahora es un objeto)
            $productosCanje = json_decode($ordenCompra->productos_canje, true);

            if (!$productosCanje || !is_array($productosCanje)) {
                DB::rollBack();
                return $this->sendError('Error al procesar los productos de la orden.', null, 500);
            }

            // Verificar que el producto existe
            if (!isset($productosCanje[$idProducto])) {
                DB::rollBack();
                return $this->sendError('No se encontró el producto en la orden de compra.', null, 404);
            }

            // Actualizar el estatus del proveedor directamente usando la clave
            $productosCanje[$idProducto]['estatus_proveedor'] = 1;

            // Guardar el objeto actualizado
            $ordenCompra->productos_canje = json_encode($productosCanje, JSON_FORCE_OBJECT);
            $ordenCompra->save();

            DB::commit();

            return $this->sendResponse([
                'orden_compra' => $ordenCompra,
                'producto_actualizado' => $productosCanje[$idProducto]
            ], 'Producto aceptado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al aceptar el producto', $th->getMessage(), 500);
        }
    }
    public function rechazarProductoOC(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $idProducto = $request->id_producto;

            $ordenCompra = OrdenCompra::whereRaw(
                "JSON_CONTAINS_PATH(productos_canje, 'one', ?)",
                ['$."' . $idProducto . '"']
            )->first();

            if (!$ordenCompra) {
                DB::rollBack();
                return $this->sendError('No se encontró una orden de compra con este producto.', null, 404);
            }

            $productosCanje = json_decode($ordenCompra->productos_canje, true);

            if (!isset($productosCanje[$idProducto])) {
                DB::rollBack();
                return $this->sendError('No se encontró el producto en la orden de compra.', null, 404);
            }

            // Actualizar estatus y agregar motivo de rechazo
            $productosCanje[$idProducto]['estatus_proveedor'] = 2;

            $ordenCompra->productos_canje = json_encode($productosCanje, JSON_FORCE_OBJECT);
            $ordenCompra->save();

            DB::commit();

            return $this->sendResponse([
                'orden_compra' => $ordenCompra,
                'producto_actualizado' => $productosCanje[$idProducto]
            ], 'Producto rechazado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al rechazar el producto', $th->getMessage(), 500);
        }
    }
    public function enviarOCAprobacion(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_orden_compra' => 'required|integer',
                'observaciones' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $orden_compra = OrdenCompra::find($request->id_orden_compra);

            if (!$orden_compra) {
                DB::rollBack();
                return $this->sendError('Esta orden de compra no existe', 'error', 404);
            }

            $orden_compra->update([
                'observaciones' => $request->observaciones,
                'estatus' => 'cotizacion_validada_por_proveedor',
            ]);

            DB::commit();
            return $this->sendResponse($orden_compra);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al enviar la orden de compra a aprobación', $th->getMessage(), 500);
        }
    }
    public function enviarOrdenCompraFileProveedor(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_orden_compra' => 'required|integer',
                'id_proveedor' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $orden_compra = OrdenCompra::find($request->id_orden_compra);
            $proveedor = CatalogoProveedores::find($request->id_proveedor);

            if (!$orden_compra) {
                DB::rollBack();
                return $this->sendError('Esta orden de compra no existe', 'error', 404);
            }

            if (!$proveedor) {
                DB::rollBack();
                return $this->sendError('El proveedor no existe', 'error', 404);
            }

            $orden_compra->update([
                'estatus' => 'orden_compra_enviada_a_proveedor',
            ]);

            DB::commit();

            $user = Auth::user();
            $log['evento'] = 'Envío de orden de compra para proveedor';
            $log['descripcion'] = "El usuario con id: {$user->id} envió una orden de compra al proveedor {$proveedor->nombre}";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            return $this->sendResponse($orden_compra);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al enviar la orden de compra a proveedor', $th->getMessage(), 500);
        }
    }
    public function rechazarCotizacionDeProveedor(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_orden_compra' => 'required|integer',
                'id_proveedor' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $orden_compra = OrdenCompra::find($request->id_orden_compra);
            $proveedor = CatalogoProveedores::find($request->id_proveedor);

            if (!$orden_compra) {
                DB::rollBack();
                return $this->sendError('Esta orden de compra no existe', 'error', 404);
            }
            if (!$proveedor) {
                DB::rollBack();
                return $this->sendError('El proveedor no existe', 'error', 404);
            }

            $orden_compra->update([
                'estatus' => 'cotizacion_rechazada',
            ]);

            DB::commit();

            $user = Auth::user();
            $log['evento'] = 'Cotización de proveedor rechazada';
            $log['descripcion'] = "El usuario con id: {$user->id} rechazó la orden de compra {$orden_compra->id} del proveedor {$proveedor->nombre}";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            return $this->sendResponse($orden_compra);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al rechazar la cotización', $th->getMessage(), 500);
        }
    }
    public function validarOrdenCompraFinal(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_orden_compra' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $orden_compra = OrdenCompra::find($request->id_orden_compra);

            if (!$orden_compra) {
                DB::rollBack();
                return $this->sendError('Esta orden de compra no existe', 'error', 404);
            }

            $orden_compra->update([
                'estatus' => 'orden_validada_por_proveedor',
            ]);

            DB::commit();
            return $this->sendResponse('Orden de compra validada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al validar la orden de compra', $th->getMessage(), 500);
        }
    }
    public function subirPDFFactura(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_orden_compra' => 'required|integer',
                'id_proveedor' => 'required|integer',
                'id_usuario' => 'required|integer',
                'pdf_factura' => 'required|file|mimes:pdf',
            ]);

            $archivoFactura = $request->file('pdf_factura');
            $nombreFactura = $archivoFactura->getClientOriginalName();
            $tipoArchivo = $archivoFactura->getClientOriginalExtension();

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $nombreUnico = time() . '_' . Str::random(10) . '_' . $nombreFactura;

            $rutaArchivo = $archivoFactura->storeAs(
                'facturas/' . $request->id_proveedor,
                $nombreUnico,
                'private'
            );

            $factura = Facturas::create([
                'id_orden_compra' => $request->id_orden_compra,
                'id_proveedor' => $request->id_proveedor,
                'id_usuario' => $request->id_usuario,
                'nombre_factura' => $nombreFactura,
                'tipo_archivo' => $tipoArchivo,
                'url_factura' => $rutaArchivo,
            ]);

            $orden_compra = OrdenCompra::find($request->id_orden_compra);

            if (!$orden_compra) {
                DB::rollBack();
                return $this->sendError('Esta orden de compra no existe', 'error', 404);
            }

            $orden_compra->update([
                'estatus' => 'factura_subida_correctamente_proveedor',
            ]);

            DB::commit();
            return $this->sendResponse([
                'factura' => $factura
            ], 'PDF de la factura subido correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al subir el PDF de la factura', $th->getMessage(), 500);
        }
    }
    public function validarFacturaOrdenCompra(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_orden_compra' => 'required|integer',
                'id_proveedor' => 'required|integer',
                'id_usuario' => 'required|integer',
                'xml_factura' => 'required|file|mimes:xml',
            ]);

            $archivoFactura = $request->file('xml_factura');
            $nombreFactura = $archivoFactura->getClientOriginalName();
            $tipoArchivo = $archivoFactura->getClientOriginalExtension();

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            // Obtener token de autenticación de SW
            $tokenResponse = $this->obtenerTokenSW();

            if (!$tokenResponse['success']) {
                DB::rollBack();
                return $this->sendError('Error al autenticar con SW', $tokenResponse['error'], 500);
            }

            $token = $tokenResponse['token'];

            //obtenemos el xml para posteriormente validarlo
            $xmlContent = file_get_contents($request->file('xml_factura')->getRealPath());

            // Validar factura
            $validacionFactura = $this->validarFacturaSW($xmlContent, $token, $nombreFactura);

            if (!$validacionFactura['success']) {
                DB::rollBack();
                return $this->sendError('Error al validar la factura', $validacionFactura['error'], 400);
            }

            $nombreUnico = time() . '_' . Str::random(10) . '_' . $nombreFactura;

            $rutaArchivo = $archivoFactura->storeAs(
                'facturas/' . $request->id_proveedor, // Organizar por proveedor
                $nombreUnico,
                'private' // Disco privado
            );

            $factura = Facturas::create([
                'id_orden_compra' => $request->id_orden_compra,
                'id_proveedor' => $request->id_proveedor,
                'id_usuario' => $request->id_usuario,
                'nombre_factura' => $nombreFactura,
                'tipo_archivo' => $tipoArchivo,
                'url_factura' => $rutaArchivo,
            ]);

            $orden_compra = OrdenCompra::find($request->id_orden_compra);

            if (!$orden_compra) {
                DB::rollBack();
                return $this->sendError('Esta orden de compra no existe', 'error', 404);
            }

            $orden_compra->update([
                'estatus' => 'xml_validado_correctamente_proveedor',
            ]);

            DB::commit();

            return $this->sendResponse([
                'factura' => $factura,
                'validacion' => $validacionFactura['data']
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al validar la factura', $th->getMessage(), 500);
        }
    }
    private function obtenerTokenSW()
    {
        try {
            $url = config('sw.authenticate_url');

            $data = [
                'user' => config('sw.user'),
                'password' => config('sw.password')
            ];

            // Debug temporal
            Log::info('SW_USER existe: ' . ($data['user'] ? 'SI' : 'NO'));
            Log::info('SW_PASSWORD existe: ' . ($data['password'] ? 'SI' : 'NO'));

            if (!$data['user'] || !$data['password']) {
                return [
                    'success' => false,
                    'error' => 'Credenciales de SW no configuradas en .env'
                ];
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $data);

            if ($response->successful()) {
                $responseData = $response->json();

                return [
                    'success' => true,
                    'token' => $responseData['data']['token'] ?? null
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Error desconocido'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    private function validarFacturaSW($xmlContent, $token, $nombreFactura)
    {
        try {
            $url = config('sw.validate_cfdi_url');

            $response = Http::withToken($token)
                ->attach(
                    'xml',
                    $xmlContent,
                    $nombreFactura
                )
                ->post($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Error al validar factura'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    public function enviarANuevoProveedor(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_proveedor' => 'required|integer',
                'id_producto' => 'required|integer',
                'id_validacion_producto' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $producto_validado = ValidacionCanje::find($request->id_validacion_producto);

            if (!$producto_validado) {
                DB::rollBack();
                return $this->sendError('Este producto no se encuentra validado', 'error', 404);
            }

            $producto_validado->update([
                'id_producto' => $request->id_producto,
                'id_proveedor' => $request->id_proveedor,
            ]);

            DB::commit();
            return $this->sendResponse('Producto enviado a otro proveedor correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al enviar el producto a otro proveedor', $th->getMessage(), 500);
        }
    }
}
