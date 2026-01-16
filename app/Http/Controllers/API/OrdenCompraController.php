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
use App\Models\OrdenCompra;
use App\Services\WhatsAppService;

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
            $query = DB::table('dc_catalogo_proveedores as cp')
                ->select(
                    'cp.id',
                    'cp.nombre',
                    'cp.razon_social',
                    'cp.descripcion',
                    'cp.nombre_contacto',
                    'cp.telefono',
                    'cp.correo',
                    DB::raw('COUNT(DISTINCT sv.id) as total_canjes')
                )
                ->join('dc_catalogo_productos as dcp', 'cp.id', '=', 'dcp.id_proveedor')
                ->join('swaps_view as sv', 'dcp.sku', '=', 'sv.sku')
                ->join('dc_validacion_canje as vc', 'sv.id', '=', 'vc.id_canje')
                ->where('sv.status', 'ACTIVE')
                ->where('vc.estatus', 'identidad_validada')
                ->groupBy(
                    'cp.id',
                    'cp.nombre',
                    'cp.razon_social',
                    'cp.descripcion',
                    'cp.nombre_contacto',
                    'cp.telefono',
                    'cp.correo'
                );

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('cp.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('cp.correo', 'LIKE', "%{$search}%");
                });
            }
            $proveedores = $query->orderBy('total_canjes', 'desc')->get();

            return $this->sendResponse($proveedores);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los proveedores', $th, 500);
        }
    }

    public function getCanjesPorProveedor(Request $request)
    {
        try {
            $query = DB::table('swaps_view as sv')
                ->select(
                    'sv.id',
                    'sv.folio',
                    'sv.name as nombre_usuario',
                    'sv.email',
                    'sv.phone',
                    'sv.number_of_awards',
                    'sv.size',
                    'sv.color',
                    'sv.category',
                    'sv.points_swap as puntos_canjeados',
                    'sv.desc as nombre_premio',
                    'sv.required_score as costo_premio',
                    'sv.sku',
                    'sv.street as calle',
                    'sv.number as numero_calle',
                    'sv.colony as colonia',
                    'sv.postal_code as codigo_postal',
                    'sv.municipality as municipio',
                    'sv.inside as numero_interior',
                    'sv.between_1',
                    'sv.between_2',
                    'sv.additional_reference as referencia_adicional',
                    'sv.created_at as creacion_canje',
                    'sv.status as estado_canje',
                    'sv.status as estado_canje',
                    'vc.estatus as estado_validacion',
                    'vc.fecha_validacion',
                    'cdp.nombre_producto',
                    'cdp.marca',
                    'cp.nombre as nombre_proveedor',
                    'cdp.fee_brimagy',
                    'cp.razon_social',
                    'cdp.costo_sin_iva',
                )
                ->leftJoin('dc_catalogo_productos as cdp', 'sv.sku', '=', 'cdp.sku')
                ->leftJoin('dc_catalogo_proveedores as cp', 'cdp.id_proveedor', '=', 'cp.id')
                ->leftJoin('dc_validacion_canje as vc', 'sv.id', '=', 'vc.id_canje')
                ->where('cp.id', $request->id_proveedor)
                ->where('vc.estatus', '=', 'identidad_validada');

            $canjes = $query->orderBy('sv.created_at', 'desc')->get();

            // Obtener todas las órdenes de compra del proveedor
            $ordenesCompra = DB::table('dc_orden_compra')
                ->where('id_proveedor', $request->id_proveedor)
                ->get();

            // Agregar el estatus_proveedor a cada canje
            $canjes = $canjes->map(function ($canje) use ($ordenesCompra) {
                $estatusProveedor = null;

                // Buscar en todas las órdenes de compra
                foreach ($ordenesCompra as $orden) {
                    $productosCanje = json_decode($orden->productos_canje, true);

                    if ($productosCanje && is_array($productosCanje)) {
                        // Buscar el canje actual en el array de productos
                        foreach ($productosCanje as $producto) {
                            if (isset($producto['id_canje']) && $producto['id_canje'] == $canje->id) {
                                $estatusProveedor = $producto['estatus_proveedor'] ?? null;
                                break 2; // Salir de ambos loops
                            }
                        }
                    }
                }

                // Agregar el estatus al objeto canje
                $canje->estatus_proveedor = $estatusProveedor;

                return $canje;
            });



            return $this->sendResponse($canjes);
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
                'canjes' => 'required|array',
                'canjes.*.id_canje' => 'required|integer',
                'canjes.*.cantidad_producto' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $canjesIds = collect($request->canjes)->pluck('id_canje')->toArray();

            $canjesData = DB::table('swaps_view as sv')
                ->select(
                    'sv.id',
                    'sv.folio',
                    'sv.name as nombre_usuario',
                    'sv.email',
                    'sv.phone',
                    'sv.number_of_awards',
                    'sv.desc as nombre_premio',
                    'sv.sku',
                    'cdp.nombre_producto',
                    'cdp.marca',
                    'cdp.fee_brimagy',
                    'cdp.costo_sin_iva',
                    'cdp.costo_con_iva',
                    'cp.nombre as nombre_proveedor',
                    'cp.razon_social',
                    'cp.telefono as telefono_proveedor',
                    'cp.correo as correo_proveedor'
                )
                ->leftJoin('dc_catalogo_productos as cdp', 'sv.sku', '=', 'cdp.sku')
                ->leftJoin('dc_catalogo_proveedores as cp', 'cdp.id_proveedor', '=', 'cp.id')
                ->whereIn('sv.id', $canjesIds)
                ->get();

            // CAMBIO IMPORTANTE: Guardar como objeto con id_canje como clave
            $productosCompletos = [];

            foreach ($canjesData as $canje) {
                $canjeRequest = collect($request->canjes)->firstWhere('id_canje', $canje->id);

                $precioUnitario = $canje->costo_sin_iva ?? 0;
                $cantidad = $canjeRequest['cantidad_producto'] ?? 0;
                $porcentajeDescuento = $canje->fee_brimagy ?? 0;

                $subtotalSinDescuento = $precioUnitario * $cantidad;
                $descuentoEnPesos = $subtotalSinDescuento * ($porcentajeDescuento / 100);
                $subtotalConDescuento = $subtotalSinDescuento - $descuentoEnPesos;
                $iva = $subtotalConDescuento * 0.16;
                $importeTotal = $subtotalConDescuento + $iva;

                // Usar id_canje como clave del array asociativo
                $productosCompletos[$canje->id] = [
                    'id_canje' => $canje->id,
                    'nombre_producto' => $canje->nombre_premio,
                    'sku' => $canje->sku,
                    'cantidad_producto' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'porcentaje_descuento' => $porcentajeDescuento,
                    'subtotal' => $subtotalConDescuento,
                    'iva' => $iva,
                    'importe_total' => $importeTotal,
                    'estatus_proveedor' => $canjeRequest['estatus_proveedor'] ?? 0,
                    'estatus_almacen' => $canjeRequest['estatus_almacen'] ?? 0,
                    'tipo_compra' => $canjeRequest['tipo_compra'] ?? '',
                ];
            }

            $proveedor = $request->id_proveedor;
            $usuario = $request->id_usuario;
            $estado = "cotizacion_enviada_a_proveedor";

            $cotizacion = OrdenCompra::create([
                'id_usuario' => $usuario,
                'id_proveedor' => $proveedor,
                'no_orden' => $this->generarNumeroOrdenUnico(),
                'productos_canje' => json_encode($productosCompletos, JSON_FORCE_OBJECT),
                'observaciones' => $request->observaciones,
                'estatus' => $estado,
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
                    'cp.correo'
                )
                ->leftJoin('dc_catalogo_proveedores as cp', 'oc.id_proveedor', '=', 'cp.id')
                ->where('oc.no_orden', $idOrdenCompraDesencriptado)
                ->orderBy('oc.created_at', 'desc')
                ->first();

            if (!$ordenCompra) {
                return $this->sendError('No se encontró la orden de compra', null, 404);
            }

            // Decodificar productos (ahora es un objeto con id_canje como clave)
            $productosOrden = json_decode($ordenCompra->productos_canje, true);

            if (!$productosOrden || !is_array($productosOrden)) {
                return $this->sendError('No se encontraron productos en la orden', null, 404);
            }

            // Extraer los IDs de los canjes desde las claves del objeto
            // Ahora los productos están indexados como: {"22610": {...}, "22685": {...}}
            $canjesIds = array_keys($productosOrden);

            // Obtener los datos completos de los canjes desde swaps_view
            $canjesData = DB::table('swaps_view as sv')
                ->select(
                    'sv.id',
                    'sv.folio',
                    'sv.name as nombre_usuario',
                    'sv.email',
                    'sv.phone',
                    'sv.number_of_awards',
                    'sv.size',
                    'sv.color',
                    'sv.category',
                    'sv.points_swap as puntos_canjeados',
                    'sv.desc as nombre_premio',
                    'sv.required_score as costo_premio',
                    'sv.sku',
                    'sv.street as calle',
                    'sv.number as numero_calle',
                    'sv.colony as colonia',
                    'sv.postal_code as codigo_postal',
                    'sv.municipality as municipio',
                    'sv.inside as numero_interior',
                    'sv.between_1',
                    'sv.between_2',
                    'sv.additional_reference as referencia_adicional',
                    'sv.created_at as creacion_canje',
                    'sv.status as estado_canje',
                    'vc.estatus as estado_validacion',
                    'vc.fecha_validacion',
                    'cdp.nombre_producto',
                    'cdp.marca',
                    'cdp.fee_brimagy',
                    'cdp.costo_sin_iva',
                    'cdp.costo_con_iva'
                )
                ->leftJoin('dc_catalogo_productos as cdp', 'sv.sku', '=', 'cdp.sku')
                ->leftJoin('dc_validacion_canje as vc', 'sv.id', '=', 'vc.id_canje')
                ->whereIn('sv.id', $canjesIds)
                ->get()
                ->keyBy('id'); // Indexar por ID para fácil acceso

            // Combinar los datos del JSON con los datos de la BD
            $productosCombinados = [];

            foreach ($productosOrden as $idCanje => $productoOrden) {
                $canjeData = $canjesData->get($idCanje);

                if (!$canjeData) {
                    continue; // Saltar si no se encuentra el canje
                }

                // Combinar datos del canje con datos del producto de la orden
                $productosCombinados[] = array_merge(
                    (array) $canjeData, // Datos del canje desde la BD
                    $productoOrden // Datos del JSON (cantidad, precios, estatus, etc.)
                );
            }

            // Convertir a colección para facilitar operaciones
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
    public function aceptarProductoOC(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_canje' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $idCanje = $request->id_canje;

            // Buscar la orden que contiene este id_canje usando JSON_CONTAINS
            $ordenCompra = OrdenCompra::whereRaw(
                "JSON_CONTAINS_PATH(productos_canje, 'one', ?)",
                ['$."' . $idCanje . '"']
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
            if (!isset($productosCanje[$idCanje])) {
                DB::rollBack();
                return $this->sendError('No se encontró el producto en la orden de compra.', null, 404);
            }

            // Actualizar el estatus del proveedor directamente usando la clave
            $productosCanje[$idCanje]['estatus_proveedor'] = 1;

            // Guardar el objeto actualizado
            $ordenCompra->productos_canje = json_encode($productosCanje, JSON_FORCE_OBJECT);
            $ordenCompra->save();

            DB::commit();

            return $this->sendResponse([
                'orden_compra' => $ordenCompra,
                'producto_actualizado' => $productosCanje[$idCanje]
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
                'id_canje' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $idCanje = $request->id_canje;

            $ordenCompra = OrdenCompra::whereRaw(
                "JSON_CONTAINS_PATH(productos_canje, 'one', ?)",
                ['$."' . $idCanje . '"']
            )->first();

            if (!$ordenCompra) {
                DB::rollBack();
                return $this->sendError('No se encontró una orden de compra con este producto.', null, 404);
            }

            $productosCanje = json_decode($ordenCompra->productos_canje, true);

            if (!isset($productosCanje[$idCanje])) {
                DB::rollBack();
                return $this->sendError('No se encontró el producto en la orden de compra.', null, 404);
            }

            // Actualizar estatus y agregar motivo de rechazo
            $productosCanje[$idCanje]['estatus_proveedor'] = 2;

            $ordenCompra->productos_canje = json_encode($productosCanje, JSON_FORCE_OBJECT);
            $ordenCompra->save();

            DB::commit();

            return $this->sendResponse([
                'orden_compra' => $ordenCompra,
                'producto_actualizado' => $productosCanje[$idCanje]
            ], 'Producto rechazado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al rechazar el producto', $th->getMessage(), 500);
        }
    }
}
