<?php

namespace App\Http\Controllers\API;

use App\Contracts\EstrategiaCotizacionMultiple;
use App\Http\Requests\CotizarOtrosProveedoresRequest;
use App\Models\Categoria;
use App\Models\SubCategoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Mail\EnvioCotizacion;
use App\Mail\EnvioOrdenCompra;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\BitacoraEventos;
use App\Models\CatalogoProveedores;
use App\Models\CategoriaClub;
use App\Models\Facturas;
use App\Models\Notificaciones;
use App\Models\OrdenCompra;
use App\Models\Plataformas;
use App\Models\RecepcionAlmacen;
use App\Models\SubCategoriaClub;
use App\Models\UsuariosPlataforma;
use App\Models\ValidacionCanje;
use App\Repositories\CanjesPendientesRepository;
use App\Services\FolioCanjeResolver;
use App\Services\OrdenCompraService;
use App\Services\WhatsAppService;
use App\Services\CierreCanjesService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;


class OrdenCompraController extends BaseController
{
    protected $whatsappService;

    public function __construct()
    {
        $this->whatsappService = new WhatsAppService();
    }

    /**
     * Combina las líneas de productos_canje con el catálogo y su validación.
     */
    private function productosDeOrden(?string $json): Collection
    {
        $lineas = collect(json_decode($json ?? '', true) ?: [])
            ->map(function ($linea, $llave) {
                $linea['_llave'] = (string) $llave;
                $linea['id_producto'] = $linea['id_producto'] ?? (int) $llave;
                return $linea;
            });

        if ($lineas->isEmpty()) {
            return collect();
        }

        $catalogo = DB::table('dc_catalogo_productos as cdp')
            ->leftJoin('dc_plataformas as p', 'p.id', '=', 'cdp.id_plataforma')
            ->whereIn('cdp.id', $lineas->pluck('id_producto')->unique())
            ->get([
                'cdp.id',
                'cdp.sku',
                'cdp.created_at as creacion_canje',
                'cdp.nombre_producto as nombre_premio',
                'cdp.marca',
                'cdp.color',
                'cdp.talla',
                'cdp.fee_brimagy',
                'cdp.costo_sin_iva',
                'cdp.costo_con_iva',
                'cdp.id_catalogo',
                'cdp.envio_base',
                'cdp.costo_caja',
                'cdp.envio_extra',
                'p.nombre as nombre_plataforma',
            ])
            ->keyBy('id');

        $validaciones = DB::table('dc_validacion_canje')
            ->whereIn('id', $lineas->pluck('id_validacion')->filter()->unique())
            ->get(['id', 'estatus', 'fecha_validacion', 'orden_ejecutada'])
            ->keyBy('id');

        return $lineas
            ->map(function ($linea) use ($catalogo, $validaciones) {
                $producto = $catalogo->get($linea['id_producto']);
                if (!$producto) {
                    return null;
                }

                $vc = $validaciones->get($linea['id_validacion'] ?? 0);

                return array_merge((array) $producto, [
                    'estado_validacion' => $vc->estatus ?? null,
                    'fecha_validacion' => $vc->fecha_validacion ?? null,
                    'orden_ejecutada' => $vc->orden_ejecutada ?? null,
                ], $linea);
            })
            ->filter()
            ->values();
    }
    public function cotizarConOtrosProveedores(
        CotizarOtrosProveedoresRequest $request,
        EstrategiaCotizacionMultiple $estrategia
    ) {
        try {
            $resultado = $estrategia->ejecutar($request->asignacionesNormalizadas(), Auth::id());

            BitacoraEventos::create([
                'evento' => 'Cotización con otros proveedores',
                'descripcion' => 'El usuario con id: ' . Auth::id() . " ({$resultado->modo}): {$resultado->mensaje}",
                'id_usuario' => Auth::id(),
            ]);

            return $this->sendResponse($resultado->toArray(), $resultado->mensaje);
        } catch (\DomainException $e) {
            return $this->sendError($e->getMessage(), null, 422);
        } catch (\Throwable $th) {
            return $this->sendError('Error al cotizar con otros proveedores', $th->getMessage(), 500);
        }
    }
    public function getConfigCotizacion()
    {
        return $this->sendResponse([
            'modo' => config('ordenes_compra.cotizacion_multiple.modo'),
        ]);
    }
    public function crearOrdenCompra(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_canje' => 'required|integer',
                'id_usuario' => 'required|integer|exists:dc_usuarios_plataforma,id',
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
                'id_plataforma' => $request->id_plataforma,
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

    public function getProveedoresOC(Request $request, CanjesPendientesRepository $repositorio)
    {
        try {
            $conteos = $repositorio->conteoPorProveedor();
            $sinProveedorCount = (int) ($conteos[0] ?? 0);

            $ordenesPorProveedor = DB::table('dc_orden_compra')
                ->selectRaw('COALESCE(id_proveedor, 0) as id_proveedor, COUNT(*) as total')
                ->groupBy(DB::raw('COALESCE(id_proveedor, 0)'))
                ->pluck('total', 'id_proveedor');

            $proveedores = DB::table('dc_catalogo_proveedores as cp')
                ->select(
                    'cp.id',
                    'cp.nombre',
                    'cp.razon_social',
                    'cp.descripcion',
                    'cp.nombre_contacto',
                    'cp.telefono',
                    'cp.correo'
                )
                ->when($request->filled('search'), function ($q) use ($request) {
                    $search = $request->search;
                    $q->where(fn($w) => $w->where('cp.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('cp.correo', 'LIKE', "%{$search}%"));
                })
                ->get()
                ->map(function ($p) use ($conteos, $ordenesPorProveedor) {
                    $p->total_canjes = (int) ($conteos[$p->id] ?? 0);
                    $p->total_ordenes_compra = (int) ($ordenesPorProveedor[$p->id] ?? 0);
                    return $p;
                })
                ->filter(fn($p) => $p->total_canjes > 0 || $p->total_ordenes_compra > 0)
                ->sortByDesc('total_canjes')
                ->values();

            $ordenesSinProveedor = (int) ($ordenesPorProveedor[0] ?? 0);

            if ($sinProveedorCount > 0 || $ordenesSinProveedor > 0) {
                $proveedores->prepend((object) [
                    'id' => null,
                    'nombre' => 'Sin proveedor',
                    'razon_social' => null,
                    'descripcion' => 'Canjes validados sin proveedor asignado',
                    'nombre_contacto' => null,
                    'telefono' => null,
                    'correo' => null,
                    'total_canjes' => $sinProveedorCount,
                    'total_ordenes_compra' => $ordenesSinProveedor,
                ]);
            }

            return $this->sendResponse($proveedores);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los proveedores', $th->getMessage(), 500);
        }
    }

    public function getOCPorId(Request $request, FolioCanjeResolver $folios)
    {
        try {
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
                    'up.id as id_usuario',
                    'up.name as nombre_vendedor',
                    'cp.correo'
                )
                ->leftJoin('dc_catalogo_proveedores as cp', 'oc.id_proveedor', '=', 'cp.id')
                ->leftJoin('dc_usuarios_plataforma as up', 'oc.id_usuario', '=', 'up.id')
                ->where('oc.id', $request->id_orden_compra)
                ->first();

            if (!$ordenCompra) {
                return $this->sendError('No se encontró la orden de compra', null, 404);
            }

            $productosCombinados = $this->productosDeOrden($ordenCompra->productos_canje);

            if ($productosCombinados->isEmpty()) {
                return $this->sendError('No se encontraron productos en la orden', null, 404);
            }

            // Resolver nombre de categoría según plataforma, agrupando para evitar N+1
            $idsCatalogoPorPlataforma = $productosCombinados
                ->groupBy('nombre_plataforma')
                ->map(fn($grupo) => collect($grupo)->pluck('id_catalogo')->filter()->unique()->values());

            $categoriasPorClave = collect();

            foreach ($idsCatalogoPorPlataforma as $nombrePlataforma => $idsCatalogo) {
                if ($idsCatalogo->isEmpty()) {
                    continue;
                }

                switch (strtolower((string) $nombrePlataforma)) {
                    case 'club bohn':
                        $subCategoriaModel = SubCategoriaClub::class;
                        $categoriaModel = CategoriaClub::class;
                        break;
                    case 'puntotes':
                        $subCategoriaModel = SubCategoria::class;
                        $categoriaModel = Categoria::class;
                        break;
                    default:
                        continue 2;
                }

                $subCategorias = $subCategoriaModel::whereIn('id', $idsCatalogo)->get()->keyBy('id');
                $idsCategoria = $subCategorias->pluck('category_id')->filter()->unique()->values();
                $categorias = $categoriaModel::whereIn('id', $idsCategoria)->pluck('desc', 'id');

                foreach ($idsCatalogo as $idCatalogo) {
                    $subCategoria = $subCategorias->get($idCatalogo);
                    $nombreCategoria = $subCategoria ? ($categorias[$subCategoria->category_id] ?? null) : null;
                    $categoriasPorClave->put($nombrePlataforma . ':' . $idCatalogo, $nombreCategoria);
                }
            }

            $productosCombinados = $productosCombinados->map(function ($producto) use ($categoriasPorClave) {
                $producto = (object) $producto;
                $producto->categoria = $categoriasPorClave->get($producto->nombre_plataforma . ':' . $producto->id_catalogo);
                return $producto;
            });

            // Folio y nombre de plataforma de cada canje (cada plataforma en su propia BD)
            $productosCombinados = $folios->enriquecer(
                $productosCombinados,
                Plataformas::pluck('nombre', 'id')
            );
            $aceptados = $productosCombinados->where('estatus_proveedor', 1);

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
                    'subtotal' => $aceptados->sum('subtotal'),
                    'iva' => $aceptados->sum('iva'),
                    'total' => $aceptados->sum('importe_total'),
                ],
                'estadisticas' => [
                    'total_productos' => $productosCombinados->count(),
                    'productos_aceptados' => $productosCombinados->where('estatus_proveedor', 1)->sum('cantidad_producto'),
                    'productos_rechazados' => $productosCombinados->where('estatus_proveedor', 2)->sum('cantidad_producto'),
                    'productos_pendientes' => $productosCombinados->where('estatus_proveedor', 0)->sum('cantidad_producto'),
                ],
            ];

            return $this->sendResponse($respuesta);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener la orden de compra', $th->getMessage(), 500);
        }
    }

    public function getOCPorIdProveedor(Request $request)
    {
        try {
            $sinProveedor = in_array($request->id_proveedor, [null, '', 'null', 'undefined'], true);

            $ordenes_compra = DB::table('dc_orden_compra as oc')
                ->select(
                    'oc.id',
                    'oc.no_orden',
                    'oc.estatus as estado_orden',
                    'oc.created_at as fecha_creacion',
                    DB::raw('JSON_LENGTH(oc.productos_canje) as total_productos')
                )
                ->when(
                    $sinProveedor,
                    fn($q) => $q->whereNull('oc.id_proveedor'),
                    fn($q) => $q->where('oc.id_proveedor', (int) $request->id_proveedor)
                )
                ->when($request->filled('search'), function ($q) use ($request) {
                    $search = $request->search;
                    $q->where(fn($w) => $w->where('oc.no_orden', 'LIKE', "%{$search}%")
                        ->orWhere('oc.estatus', 'LIKE', "%{$search}%"));
                })
                ->orderBy('oc.created_at', 'desc')
                ->get();

            return $this->sendResponse($ordenes_compra);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener las ordenes de compra', $th->getMessage(), 500);
        }
    }

    public function getCanjesPorProveedor(
        Request $request,
        CanjesPendientesRepository $repositorio,
        FolioCanjeResolver $folios
    ) {
        try {
            $plataformas = Plataformas::pluck('nombre', 'id');

            $idProveedor = in_array($request->id_proveedor, [null, '', 'null', 'undefined', '0', 0], true)
                ? null
                : (int) $request->id_proveedor;

            $canjes = $repositorio->porProveedor($idProveedor);

            return $this->sendResponse([
                'productos' => $folios->enriquecer($canjes, $plataformas),
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los canjes', $th->getMessage(), 500);
        }
    }

    public function enviarCotizacionProveedor(Request $request, OrdenCompraService $servicio)
    {
        $validator = Validator::make($request->all(), [
            'id_usuario' => 'required|integer|exists:dc_usuarios_plataforma,id',
            'id_proveedor' => 'nullable|integer|exists:dc_catalogo_proveedores,id',
            'tipo_envio' => 'required|in:directo,proveedor',
            'productos' => 'required|array|min:1',
            'productos.*.id_validacion' => 'required|integer|exists:dc_validacion_canje,id',
            'productos.*.estatus_proveedor' => 'nullable|integer|in:0,1,2',
            'productos.*.estatus_almacen' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('El formato de datos no es válido.', $validator->errors());
        }

        try {
            $orden = DB::transaction(function () use ($request, $servicio) {
                $orden = $servicio->crearOrden(
                    $request->productos,
                    $request->id_proveedor,
                    $request->id_usuario,
                    $request->tipo_envio,
                    $request->observaciones
                );

                BitacoraEventos::create([
                    'evento' => 'Envío de cotización para proveedor',
                    'descripcion' => 'El usuario con id: ' . Auth::id() . " creó la orden {$orden->no_orden} para el proveedor {$request->id_proveedor}",
                    'id_usuario' => Auth::id(),
                ]);

                return $orden;
            });
        } catch (\DomainException $e) {
            return $this->sendError($e->getMessage(), null, 422);
        } catch (\Throwable $th) {
            return $this->sendError('Error al enviar la cotización', $th->getMessage(), 500);
        }

        $this->notificarOrden($orden, $request->tipo_envio);

        return $this->sendResponse($orden, 'Cotización enviada exitosamente.');
    }

    private function notificarOrden(OrdenCompra $orden, string $tipoEnvio): void
    {
        try {
            $productos = array_values(json_decode($orden->productos_canje, true) ?: []);
            $args = [$productos, $orden->id_proveedor, $orden->id_usuario, $orden->estatus, $orden->no_orden];

            if ($tipoEnvio === 'directo') {
                $this->enviarWhatsApp(...$args, ...['directo']);
            } else {
                $this->enviarWhatsApp(...$args);
                $this->enviarCorreo(...$args);
            }
        } catch (\Throwable $th) {
            Log::error("Error al notificar la orden {$orden->no_orden}: " . $th->getMessage());
        }
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
    private function enviarWhatsApp($canje, $proveedor = null, $usuario = null, $estado = null, $no_orden = null, $tipo = null)
    {
        try {
            $telefonoProveedor = CatalogoProveedores::where('id', $proveedor)
                ->whereNotNull('telefono')
                ->where('telefono', '!=', '')
                ->value('telefono');
            $telefonosAdmins = UsuariosPlataforma::where('tipo_usuario', 7)
                ->whereNotNull('telefono')
                ->where('telefono', '!=', '')
                ->pluck('telefono')
                ->toArray();

            $canjeIdEncriptado = $this->encriptarCorto($no_orden);
            $urlva = config('app.url_frontend') . "/validar-ordencompra/{$canjeIdEncriptado}";

            if ($estado === "cotizacion_enviada_a_proveedor" && $tipo !== 'directo') {

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

                $this->whatsappService->sendMessage($telefonoProveedor, $mensaje);
            } else if ($estado === "orden_compra_enviada_a_proveedor" && $tipo !== 'directo') {

                $mensaje = "Se le ha enviado una orden de compra para su validación:\n\n";
                $mensaje .= "📦 *Premios:*\n";

                $totalGeneral = 0;
                foreach ($canje as $producto) {
                    $mensaje .= "• {$producto['nombre_producto']}\n";
                    $mensaje .= "  Cantidad: {$producto['cantidad_producto']}\n";
                    $mensaje .= "  Precio: $" . number_format($producto['precio_unitario'], 2) . "\n";
                    $mensaje .= "  Total: $" . number_format($producto['importe_total'], 2) . "\n\n";
                    $totalGeneral += $producto['importe_total'];
                }

                $mensaje .= "💰 *Total: $" . number_format($totalGeneral, 2) . "*\n\n";
                $mensaje .= "🔗 Link de la orden: \n{$urlva}\n\n";

                $this->whatsappService->sendMessage($telefonoProveedor, $mensaje);
            } else if ($tipo == 'directo') {

                $mensaje = "Se ha validado una orden de compra directamente:\n\n";
                $mensaje .= "📦 *Productos:*\n";

                $totalGeneral = 0;
                foreach ($canje as $producto) {
                    $mensaje .= "• {$producto['nombre_producto']}\n";
                    $mensaje .= "  Cantidad: {$producto['cantidad_producto']}\n";
                    $mensaje .= "  Precio: $" . number_format($producto['precio_unitario'], 2) . "\n";
                    $mensaje .= "  Total: $" . number_format($producto['importe_total'], 2) . "\n\n";
                    $totalGeneral += $producto['importe_total'];
                }

                $mensaje .= "💰 *Total General: $" . number_format($totalGeneral, 2) . "*\n\n";

                foreach ($telefonosAdmins as $telefono) {
                    $this->whatsappService->sendMessage($telefono, $mensaje);
                }
            }
            return $this->sendResponse('Cotización enviada exitosamente.');
        } catch (\Throwable $th) {
            return $this->sendError('Error al enviar la cotización', $th->getMessage(), 500);
        }
    }

    private function enviarCorreo($canje, $proveedor = null, $usuario = null, $estado = null, $no_orden = null, $tipo = null)
    {
        try {
            $correoProveedor = CatalogoProveedores::where('id', $proveedor)
                ->whereNotNull('correo')
                ->where('correo', '!=', '')
                ->value('correo');

            $totalGeneral = collect($canje)->sum('importe_total');
            $totalIva = collect($canje)->sum('iva');
            $ordenIdEncriptado = $this->encriptarCorto($no_orden);
            $urlva = config('app.url_frontend') . "/validar-ordencompra/{$ordenIdEncriptado}";

            $productosData = (object) [
                'productos' => $canje,
                'total_general' => $totalGeneral,
                'total_iva' => $totalIva,
                'url_validacion' => $urlva,
            ];

            if ($estado === "cotizacion_enviada_a_proveedor") {
                Mail::to($correoProveedor)->send(new EnvioCotizacion($productosData, $urlva));
            } else if ($estado === "orden_compra_enviada_a_proveedor") {
                Mail::to($correoProveedor)->send(new EnvioOrdenCompra($productosData, $urlva));
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
                    'up.id as id_usuario',
                    'up.name as nombre_vendedor',
                    'cp.correo'
                )
                ->leftJoin('dc_catalogo_proveedores as cp', 'oc.id_proveedor', '=', 'cp.id')
                ->leftJoin('dc_usuarios_plataforma as up', 'oc.id_usuario', '=', 'up.id')
                ->where('oc.no_orden', $idOrdenCompraDesencriptado)
                ->first();

            if (!$ordenCompra) {
                return $this->sendError('No se encontró la orden de compra', null, 404);
            }

            $productosCombinados = $this->productosDeOrden($ordenCompra->productos_canje);

            if ($productosCombinados->isEmpty()) {
                return $this->sendError('No se encontraron productos en la orden', null, 404);
            }

            $aceptados = $productosCombinados->where('estatus_proveedor', 1);

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
                    'subtotal' => $aceptados->sum('subtotal'),
                    'iva' => $aceptados->sum('iva'),
                    'total' => $aceptados->sum('importe_total'),
                ],
                'estadisticas' => [
                    'total_productos' => $productosCombinados->count(),
                    'productos_aceptados' => $productosCombinados->where('estatus_proveedor', 1)->sum('cantidad_producto'),
                    'productos_rechazados' => $productosCombinados->where('estatus_proveedor', 2)->sum('cantidad_producto'),
                    'productos_pendientes' => $productosCombinados->where('estatus_proveedor', 0)->sum('cantidad_producto'),
                ],
            ];

            return $this->sendResponse($respuesta);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener la orden de compra', $th->getMessage(), 500);
        }
    }
    public function aceptarProductoOC(Request $request)
    {
        return $this->cambiarEstatusProductoOC($request, 1, 'aceptado');
    }

    public function rechazarProductoOC(Request $request)
    {
        return $this->cambiarEstatusProductoOC($request, 2, 'rechazado');
    }
    private function cambiarEstatusProductoOC(Request $request, int $nuevoEstatus, string $accion)
    {
        $validator = Validator::make($request->all(), [
            'id_orden_compra' => 'required|integer',
            'llave' => 'required_without:id_producto',
            'id_producto' => 'required_without:llave|integer',
            'motivo' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('El formato de datos no es válido.', $validator->errors());
        }

        $llave = (string) ($request->llave ?? $request->id_producto);

        DB::beginTransaction();

        try {
            $ordenCompra = OrdenCompra::where('id', $request->id_orden_compra)
                ->lockForUpdate()
                ->first();

            if (!$ordenCompra) {
                DB::rollBack();
                return $this->sendError('La orden de compra no existe.', null, 404);
            }

            if ($ordenCompra->estatus === 'orden_compra_enviada_a_proveedor') {
                DB::rollBack();
                return $this->sendError('La orden ya fue enviada al proveedor, no se puede modificar.', null, 409);
            }

            $productosCanje = json_decode($ordenCompra->productos_canje, true);

            if (!is_array($productosCanje) || !isset($productosCanje[$llave])) {
                DB::rollBack();
                return $this->sendError('No se encontró el producto en esta orden de compra.', null, 404);
            }

            $productosCanje[$llave]['estatus_proveedor'] = $nuevoEstatus;

            if ($nuevoEstatus === 2) {
                $productosCanje[$llave]['motivo_rechazo'] = $request->motivo ?? '';
            }

            $ordenCompra->productos_canje = json_encode($productosCanje, JSON_FORCE_OBJECT);
            $ordenCompra->save();

            DB::commit();

            return $this->sendResponse([
                'orden_compra' => $ordenCompra,
                'producto_actualizado' => $productosCanje[$llave],
            ], "Producto {$accion} exitosamente.");
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError("Error al marcar el producto como {$accion}", $th->getMessage(), 500);
        }
    }
    public function enviarOCAprobacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_orden_compra' => 'required|integer',
            'observaciones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('El formato de datos no es válido.', $validator->errors());
        }

        try {
            [$orden, $destinatarios, $detalle] = DB::transaction(function () use ($request) {
                $orden = OrdenCompra::lockForUpdate()->find($request->id_orden_compra);

                if (!$orden) {
                    throw new \DomainException('Esta orden de compra no existe');
                }

                // Evita doble envío y notificaciones duplicadas
                if ($orden->estatus !== 'cotizacion_enviada_a_proveedor') {
                    throw new \DomainException('Esta cotización ya fue enviada a aprobación.');
                }

                $proveedor = CatalogoProveedores::find($orden->id_proveedor);
                if (!$proveedor) {
                    throw new \DomainException('No existe el proveedor');
                }

                $orden->update([
                    'observaciones' => $request->observaciones,
                    'estatus' => 'cotizacion_validada_por_proveedor',
                ]);

                // Resumen de la respuesta para que quien revisa sepa qué esperar
                $lineas = collect(json_decode($orden->productos_canje, true) ?: []);
                $disponibles = $lineas->where('estatus_proveedor', 1)->count();
                $noDisponibles = $lineas->where('estatus_proveedor', 2)->count();

                $detalle = "El proveedor {$proveedor->nombre} respondió la cotización {$orden->no_orden}: "
                    . "{$disponibles} disponible(s), {$noDisponibles} no disponible(s). Pendiente de revisión.";

                // Quien creó la orden + administradores, sin repetir
                $destinatarios = UsuariosPlataforma::where('tipo_usuario', 7)
                    ->pluck('id')
                    ->push($orden->id_usuario)
                    ->filter()
                    ->unique()
                    ->values();

                foreach ($destinatarios as $idUsuario) {
                    Notificaciones::create([
                        'vista' => 0,
                        'creada' => now(),
                        'detalle' => $detalle,
                        'id_tipo_notificacion' => 1,
                        'id_usuario_creador' => $orden->id_proveedor,
                        'id_usuario_para' => $idUsuario,
                    ]);
                }

                return [$orden, $destinatarios, $detalle];
            });
        } catch (\DomainException $e) {
            return $this->sendError($e->getMessage(), null, 422);
        } catch (\Throwable $th) {
            return $this->sendError('Error al enviar la orden de compra a aprobación', $th->getMessage(), 500);
        }

        // Fuera de la transacción: si WhatsApp falla, la aprobación ya quedó guardada
        $this->notificarRevisionWhatsApp($destinatarios, $detalle);

        return $this->sendResponse($orden, 'Cotización enviada a aprobación.');
    }

    private function notificarRevisionWhatsApp(Collection $destinatarios, string $detalle): void
    {
        try {
            $telefonos = UsuariosPlataforma::whereIn('id', $destinatarios)
                ->whereNotNull('telefono')
                ->where('telefono', '!=', '')
                ->pluck('telefono')
                ->unique();

            $url = config('app.url_frontend') . '/generar-orden-compra';

            $mensaje = "📋 *Cotización pendiente de revisión*\n\n{$detalle}\n\n🔗 Revísala en:\n{$url}";

            foreach ($telefonos as $telefono) {
                $this->whatsappService->sendMessage($telefono, $mensaje);
            }
        } catch (\Throwable $th) {
            Log::error('Error al notificar revisión por WhatsApp: ' . $th->getMessage());
        }
    }
    public function enviarOrdenCompraFileProveedor(Request $request, CierreCanjesService $cierre)
    {
        $validator = Validator::make($request->all(), [
            'id_orden_compra' => 'required|integer',
            'id_proveedor' => 'required|integer|exists:dc_catalogo_proveedores,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('El formato de datos no es válido.', $validator->errors());
        }

        $proveedor = CatalogoProveedores::find($request->id_proveedor);
        $estado = 'orden_compra_enviada_a_proveedor';

        try {
            [$orden_compra, $productosParaEnvio] = DB::transaction(function () use ($request, $estado, $cierre) {
                $orden_compra = OrdenCompra::lockForUpdate()->find($request->id_orden_compra);

                if (!$orden_compra) {
                    throw new \DomainException('Esta orden de compra no existe');
                }

                // Evita duplicar la recepción en almacén si se presiona dos veces
                if ($orden_compra->estatus === $estado) {
                    throw new \DomainException('Esta orden ya fue enviada al proveedor');
                }

                // Evita comprar el mismo producto de canje a dos proveedores
                $cierre->asegurarSinCompraDuplicada($orden_compra);

                $productos = $this->productosDeOrden($orden_compra->productos_canje);

                if ($productos->isEmpty()) {
                    throw new \DomainException('No se encontraron productos en la orden');
                }

                $productosParaEnvio = [];

                foreach ($productos as $p) {
                    if ((int) ($p['estatus_proveedor'] ?? 0) !== 1) {
                        continue;
                    }

                    $productosParaEnvio[] = $p;

                    RecepcionAlmacen::create([
                        'id_canje' => $p['id_canje'],
                        'id_usuario' => $orden_compra->id_usuario,
                        'id_producto' => $p['id_producto'],
                        'id_orden_compra' => $orden_compra->id,
                        'cantidad_producto' => $p['cantidad_producto'],
                        'cantidad_almacen' => 0,
                        'id_proveedor' => $request->id_proveedor,
                        'precio_compra' => $p['precio_unitario'] ?? null,
                        'fecha_compra' => null,
                        'costo_envio_real' => ($p['envio_base'] ?? 0) + ($p['costo_caja'] ?? 0) + ($p['envio_extra'] ?? 0),
                        'comentarios' => '',
                        'guia' => '',
                        'cantidad' => $p['cantidad_producto'] ?? 1,
                        'sku' => $p['sku'],
                        'nombre_producto' => $p['nombre_producto'] ?? $p['nombre_premio'],
                    ]);
                }

                $orden_compra->update(['estatus' => $estado]);

                BitacoraEventos::create([
                    'evento' => 'Envío de orden de compra para proveedor',
                    'descripcion' => 'El usuario con id: ' . Auth::id() . " envió la orden {$orden_compra->no_orden} al proveedor {$request->id_proveedor}",
                    'id_usuario' => Auth::id(),
                ]);

                return [$orden_compra, $productosParaEnvio];
            });
        } catch (\DomainException $e) {
            return $this->sendError($e->getMessage(), null, 422);
        } catch (\Throwable $th) {
            return $this->sendError('Error al enviar la orden de compra a proveedor', $th->getMessage(), 500);
        }

        if (!empty($productosParaEnvio)) {
            try {
                $this->enviarWhatsApp($productosParaEnvio, $proveedor->id, null, $estado, $orden_compra->no_orden);
                $this->enviarCorreo($productosParaEnvio, $proveedor->id, null, $estado, $orden_compra->no_orden);
            } catch (\Throwable $th) {
                Log::error("Error al notificar la orden {$orden_compra->no_orden}: " . $th->getMessage());
            }
        }

        return $this->sendResponse($orden_compra);
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
    public function validarOrdenCompraFinal(Request $request, CierreCanjesService $cierre)
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

            if (
                !$request->has('id_proveedor') ||
                $request->id_proveedor === null ||
                $request->id_proveedor === 'null'
            ) {
                $textoBitacora = "Se ha validado la orden {$orden_compra->no_orden} directamente sin proveedor";
            } else {
                $proveedor = CatalogoProveedores::find($orden_compra->id_proveedor);
                if (!$proveedor) {
                    DB::rollBack();
                    return $this->sendError('No existe el proveedor', 'error', 404);
                }
                $textoBitacora = "El proveedor {$proveedor->nombre} ha validado la orden {$orden_compra->no_orden}";
            }



            $orden_compra->update([
                'estatus' => 'orden_validada_por_proveedor',
            ]);

            $cierre->cerrarPorOrden($orden_compra);

            $log['vista'] = 0;
            $log['detalle'] = $textoBitacora;
            $log['id_tipo_notificacion'] = 2;
            $log['id_usuario_creador'] = $orden_compra->id_proveedor;
            $log['id_usuario_para'] = $orden_compra->id_usuario;
            Notificaciones::create($log);

            DB::commit();
            return $this->sendResponse('Orden de compra validada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al validar la orden de compra', $th->getMessage(), 500);
        }
    }
    public function subirPDFFactura(Request $request, CierreCanjesService $cierre)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_orden_compra' => 'required|integer',
                'id_proveedor' => 'nullable',
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
            $idProveedor = ($request->id_proveedor === 'null' || $request->id_proveedor === '' || $request->id_proveedor === null)
                ? null
                : (int) $request->id_proveedor;
            $nombreUnico = time() . '_' . Str::random(10) . '_' . $nombreFactura;
            $carpetaProveedor = $idProveedor ?? 'sin-proveedor';

            $rutaArchivo = $archivoFactura->storeAs(
                'facturas/' . $carpetaProveedor,
                $nombreUnico,
                'private'
            );

            $factura = Facturas::create([
                'id_orden_compra' => $request->id_orden_compra,
                'id_proveedor' => $idProveedor,
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

            // Factura completa (XML + PDF): el producto del canje queda ejecutado
            $cierre->cerrarPorOrden($orden_compra);

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
                'id_proveedor' => 'nullable',
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
            $idProveedor = ($request->id_proveedor === 'null' || $request->id_proveedor === '' || $request->id_proveedor === null)
                ? null
                : (int) $request->id_proveedor;
            $nombreUnico = time() . '_' . Str::random(10) . '_' . $nombreFactura;
            $carpetaProveedor = $idProveedor ?? 'sin-proveedor';

            $rutaArchivo = $archivoFactura->storeAs(
                'facturas/' . $carpetaProveedor,
                $nombreUnico,
                'private'
            );

            //$orden_compra = OrdenCompra::find($request->id_orden_compra);
            $orden_compra = OrdenCompra::lockForUpdate()->find($request->id_orden_compra);

            if (!$orden_compra) {
                DB::rollBack();
                return $this->sendError('Esta orden de compra no existe', 'error', 404);
            }

            // LOG temporal
            Log::info('Orden encontrada justo antes del insert', [
                'id' => $orden_compra->id,
                'connection' => $orden_compra->getConnectionName(),
                'timestamp' => now()->toDateTimeString(),
            ]);

            $factura = Facturas::create([
                'id_orden_compra' => $orden_compra->id,
                'id_proveedor' => $idProveedor,
                'id_usuario' => $request->id_usuario,
                'nombre_factura' => $nombreFactura,
                'tipo_archivo' => $tipoArchivo,
                'url_factura' => $rutaArchivo,
                'estatus' => "sin_pagar",
            ]);

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
            return response()->json([
                'error' => 'Ocurrió un error',
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 500);
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
