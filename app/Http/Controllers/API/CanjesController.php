<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Mail\IdentidadValidada;
use App\Mail\SolicitarCodigo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\BitacoraEventos;
use App\Models\ValidacionCanje;
use App\Mail\ValidacionCanjeEnviada;
use App\Models\Plataformas;
use App\Models\UsuariosPlataforma;
use App\Services\WhatsAppService;

use App\Http\Controllers\API\Concerns\RespondeConServicios;
use App\Http\Requests\Canjes\CanjesPorEstatusRequest;
use App\Services\Canjes\CanjesPorEstatusService;
use App\Services\Canjes\ConsultaCanjesService;
use App\Services\Canjes\FiltrosCanjes;

class CanjesController extends BaseController
{
    use RespondeConServicios;
    protected $whatsappService;

    public function __construct()
    {
        $this->whatsappService = new WhatsAppService();
    }

    /**
     * Deja en $canje solo los productos cuyos ids de catálogo se indican.
     */
    private function soloProductosValidados($canje, array $idsCatalogo)
    {
        $awardIds = DB::table('dc_catalogo_productos')
            ->whereIn('id', array_filter($idsCatalogo))
            ->pluck('id_producto_brimagy')
            ->map(fn($id) => (int) $id)
            ->all();

        $canje->productos = collect($canje->productos)
            ->filter(fn($p) => in_array((int) $p->award_id, $awardIds, true))
            ->values();

        $canje->premios = $canje->productos->pluck('nombre')->filter()->implode(', ');
        $canje->puntos_total = $canje->productos->sum('puntos');

        return $canje;
    }
    /** Productos de este canje que tienen validación, dentro de la plataforma del canje */
    private function awardIdsValidados(int $idCanje, int $idPlataforma): array
    {
        $idsCatalogo = ValidacionCanje::where('id_canje', $idCanje)
            ->where('id_plataforma', $idPlataforma)
            ->pluck('id_producto')
            ->unique();

        return DB::table('dc_catalogo_productos')
            ->whereIn('id', $idsCatalogo)
            ->pluck('id_producto_brimagy')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    public function obtenerCodigoValidacion(Request $request)
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

            $codigoValidacion = $this->generarCodigoUnico();

            $producto = ValidacionCanje::create([
                'id_canje' => $request->id_canje,
                'id_usuario_admin' => $request->id_usuario_admin,
                'codigo_validacion' => $codigoValidacion,
            ]);

            $user = Auth::user();
            $log['evento'] = 'Creación de validación de cliente';
            $log['descripcion'] = "El usuario con id: {$user->id} envió una validación al cliente {$request->nombre_usuario}";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($producto, 'Validación enviada exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar el producto', $th->getMessage(), 500);
        }
    }

    public function enviarValidacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'productos' => 'required|array|min:1',
            'productos.*.id_producto' => 'required|integer',
            'productos.*.number_of_awards' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('El formato de datos no es válido.', $validator->errors());
        }

        $plataforma = $request->plataforma === 'club_bohn' ? 'club bohn' : $request->plataforma;
        $plataformaModel = Plataformas::where('nombre', $plataforma)->first();
        if (!$plataformaModel) {
            return $this->sendError('La plataforma ' . $request->plataforma . ' no existe', 'error', 404);
        }
        $id_plataforma = $plataformaModel->id;
        $user = Auth::user();

        try {
            DB::transaction(function () use ($request, $user, $id_plataforma) {
                $this->registrarProductos($request, $user->id, $id_plataforma);

                BitacoraEventos::create([
                    'evento' => 'Creación de validación de cliente',
                    'descripcion' => "El usuario con id: {$user->id} envió un mensaje para validar el canje: {$request->id}",
                    'id_usuario' => $user->id,
                ]);
            });
        } catch (\Throwable $th) {
            return $this->sendError('Error al enviar la validación', $th->getMessage(), 500);
        }

        $this->notificarValidacion($request, $id_plataforma, null);

        return $this->sendResponse('Validación enviada exitosamente.');
    }

    private function generarCodigoUnico()
    {
        $codigo = random_int(100000, 999999);
        return $codigo;
    }

    private function obtenerCanje($id, bool $esClubBohn)
    {
        $tabla = $esClubBohn
            ? DB::connection('mysql_club_bohn')->table('swaps_view')
            : DB::table('swaps_view');

        $filas = $tabla->where('id', $id)->get();
        $canje = $filas->first();

        if (!$canje) {
            return null;
        }

        $canje->premios = $filas->pluck('desc')->filter()->implode(', ');
        $canje->puntos_total = $filas->sum('points_swap');
        $canje->productos = $filas->map(fn($f) => (object) [
            'award_id' => $f->award_id,
            'nombre' => $f->desc,
            'cantidad' => $f->number_of_awards,
            'puntos' => $f->points_swap,
        ])->values();

        return $canje;
    }
    /**
     * Crea una fila en dc_validacion_canje por cada producto que aún no la tenga.
     */
    private function registrarProductos(Request $request, int $userId, int $idPlataforma, array $extra = []): void
    {
        foreach ($request->input('productos', []) as $p) {
            ValidacionCanje::firstOrCreate(
                ['id_canje' => $request->id, 'id_producto' => $p['id_producto']],
                array_merge([
                    'cantidad_producto' => $p['number_of_awards'],
                    'id_proveedor' => $p['id_proveedor'] ?? null,
                    'id_usuario_admin' => $userId,
                    'id_plataforma' => $idPlataforma,
                ], $extra)
            );
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
    public function getCanjes(Request $request, ConsultaCanjesService $consulta)
    {
        return $this->responder(
            fn() => $this->sendResponse($consulta->consultar(FiltrosCanjes::desdeRequest($request))),
            'Error al obtener los canjes'
        );
    }

    /**
     * Conteo de canjes por estatus de validación
     */
    public function getResumenCanjes(Request $request, CanjesPorEstatusService $servicio)
    {
        return $this->responder(
            fn() => $this->sendResponse($servicio->resumen(FiltrosCanjes::desdeRequest($request))),
            'Error al obtener el resumen de canjes'
        );
    }

    public function getCanjesPorEstatus(CanjesPorEstatusRequest $request, CanjesPorEstatusService $servicio)
    {
        return $this->responder(
            fn() => $this->sendResponse($servicio->listar($request->filtros(), $request->estatus())),
            'Error al obtener los canjes por estatus'
        );
    }

    public function getCanjeById(Request $request)
    {
        try {
            $idCanje = (int) $this->desencriptarCorto($request->id_canje);

            $plataformaModel = Plataformas::find($request->plataforma);
            if (!$plataformaModel) {
                return $this->sendError('La plataforma no existe', null, 404);
            }

            $esClubBohn = $plataformaModel->nombre === 'club bohn';

            // Una fila por producto del canje
            $filas = ($esClubBohn ? DB::connection('mysql_club_bohn') : DB::connection())
                ->table('swaps_view as sp')
                ->select(ConsultaCanjesService::COLUMNAS)
                ->where('sp.id', $idCanje)
                ->get();

            if ($filas->isEmpty()) {
                return $this->sendError('Canje no encontrado', null, 404);
            }

            // Solo los productos que se enviaron a validar
            $awardIds = $this->awardIdsValidados($idCanje, $plataformaModel->id);

            $filas = $filas
                ->filter(fn($f) => in_array((int) $f->award_id, $awardIds, true))
                ->values();

            if ($filas->isEmpty()) {
                return $this->sendError('El canje no tiene productos en validación', null, 404);
            }

            $primera = $filas->first();

            // Estatus del canje: la validación más reciente
            $validacion = DB::table('dc_validacion_canje as vc')
                ->join('dc_catalogo_productos as cdp', 'cdp.id', '=', 'vc.id_producto')
                ->leftJoin('dc_plataformas as p', 'p.id', '=', 'cdp.id_plataforma')
                ->where('vc.id_canje', $idCanje)
                ->where('cdp.id_plataforma', $plataformaModel->id)
                ->orderBy('vc.id', 'desc')
                ->select('vc.estatus', 'vc.codigo_validacion', 'p.nombre as nombre_plataforma')
                ->first();

            // Campos que cambian de un producto a otro
            $camposProducto = [
                'award_id',
                'number_of_awards',
                'size',
                'color',
                'category',
                'puntos_canjeados',
                'nombre_premio',
                'costo_premio',
                'sku',
            ];

            $productos = $filas->map(
                fn($f) => collect((array) $f)->only($camposProducto)->all()
            )->values();

            $canje = (object) array_merge(
                collect((array) $primera)->except($camposProducto)->all(),
                [
                    'estado_validacion' => $validacion->estatus ?? null,
                    'nombre_plataforma' => $validacion->nombre_plataforma ?? $plataformaModel->nombre,
                    'codigo_validacion' => $validacion->codigo_validacion ?? null,
                    'productos' => $productos,
                    'total_puntos' => $filas->sum('puntos_canjeados'),
                    'total_premios' => $filas->sum('number_of_awards'),
                    'nombre_premio' => $filas->pluck('nombre_premio')->filter()->implode(', '),
                    'puntos_canjeados' => $filas->sum('puntos_canjeados'),
                ]
            );

            return $this->sendResponse($canje, 'Canje encontrado');
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener el canje', $th->getMessage(), 500);
        }
    }
    public function solicitarCodigoValidacion(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $validacionExistente = ValidacionCanje::where('id_canje', $request->id)->first();

            if (!$validacionExistente) {
                DB::rollBack();
                return $this->sendError('No existe la validación del canje', null, 404);
            }

            $plataformaModel = Plataformas::find($validacionExistente->id_plataforma);
            if (!$plataformaModel) {
                DB::rollBack();
                return $this->sendError('La plataforma no existe', null, 404);
            }

            $esClubBohn = $plataformaModel->nombre === 'club bohn';
            $plataforma = $plataformaModel->nombre;
            $idPlataforma = $plataformaModel->id;

            if ($validacionExistente && !empty($validacionExistente->visible)) {
                DB::commit();
                return $this->sendResponse($validacionExistente, 'Código de validación existente recuperado.');
            }

            $codigoValidacion = $this->generarCodigoUnico();

            ValidacionCanje::where('id_canje', $request->id)->update([
                'codigo_validacion' => $codigoValidacion,
                'estatus' => 'solicitud_enviada',
            ]);
            $validacion = ValidacionCanje::where('id_canje', $request->id)->first();

            $canje = $this->obtenerCanje($request->id, $esClubBohn);

            if ($canje) {
                $this->enviarWhatsApp($canje, $codigoValidacion, null, null, $idPlataforma);
                $this->enviarCorreo($canje, $codigoValidacion, null, null, $idPlataforma);
            }

            $log['evento'] = 'Creación de código de validación';
            $log['descripcion'] = "El cliente {$request->nombre_cliente} ha solicitado un código de validación";
            $log['id_usuario'] = $request->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($validacion, 'Validación enviada exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al enviar la validación', $th->getMessage(), 500);
        }
    }
    public function getCodigoVerificacionById(Request $request)
    {
        try {
            $idCanjeDesencriptado = $this->desencriptarCorto($request->id_canje);

            $codigoVerificacion = ValidacionCanje::where('id_canje', $idCanjeDesencriptado)
                ->whereNotNull('codigo_validacion')
                ->where('codigo_validacion', '!=', '')
                ->first();

            if (!$codigoVerificacion) {
                return $this->sendError('Canje aún no validado', null, 404);
            }

            return $this->sendResponse($codigoVerificacion->estatus, 'Código encontrado');
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener el canje', $th->getMessage(), 500);
        }
    }
    public function validarIdentidadPorCodigo(Request $request)
    {
        try {
            $validacionPorCodigo = ValidacionCanje::where('id_canje', $request->id_canje)
                ->where('codigo_validacion', $request->codigo)
                ->first();

            if (!$validacionPorCodigo) {
                return $this->sendError('No coincide el código de validación', null, 500);
            }

            $plataformaDatos = Plataformas::find($validacionPorCodigo->id_plataforma);
            if (!$plataformaDatos) {
                return $this->sendError('La plataforma no existe', null, 404);
            }
            $nombrePlataforma = $plataformaDatos->nombre;
            $idPlataforma = $plataformaDatos->id;

            $actualizadas = ValidacionCanje::where('id_canje', $request->id_canje)
                ->where('estatus', '!=', 'identidad_validada')
                ->update([
                    'estatus' => 'identidad_validada',
                    'fecha_validacion' => now()->setTimezone('America/Mexico_City'),
                ]);
            if ($actualizadas === 0) {
                return $this->sendResponse(0, 'El canje ya estaba validado');
            }

            $canje = $this->obtenerCanje($request->id_canje, $nombrePlataforma === 'club bohn');

            if ($canje) {
                $idsValidados = ValidacionCanje::where('id_canje', $request->id_canje)
                    ->where('id_plataforma', $idPlataforma)
                    ->pluck('id_producto')
                    ->all();

                $canje = $this->soloProductosValidados($canje, $idsValidados);

                if ($canje->productos->isNotEmpty()) {
                    $this->enviarWhatsApp($canje, null, true, null, $idPlataforma);
                    $this->enviarCorreo($canje, null, true, null, $idPlataforma);
                }
            }

            return $this->sendResponse($actualizadas, 'Canje validado correctamente');
        } catch (\Throwable $th) {
            return $this->sendError('Error al validar el canje', $th->getMessage(), 500);
        }
    }

    public function enviarValidacionSinProveedor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'productos' => 'required|array|min:1',
            'productos.*.id_producto' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('El formato de datos no es válido.', $validator->errors());
        }

        $plataforma = $request->plataforma === 'club_bohn' ? 'club bohn' : $request->plataforma;
        $plataformaModel = Plataformas::where('nombre', $plataforma)->first();
        if (!$plataformaModel) {
            return $this->sendError('La plataforma no existe', null, 404);
        }
        $id_plataforma = $plataformaModel->id;
        $user = Auth::user();

        try {
            DB::transaction(function () use ($request, $user, $id_plataforma) {
                $this->registrarProductos($request, $user->id, $id_plataforma);

                // Solo las validaciones de ESTE canje en ESTA plataforma
                ValidacionCanje::where('id_canje', $request->id)
                    ->whereIn('id_producto', function ($q) use ($id_plataforma) {
                        $q->select('id')
                            ->from('dc_catalogo_productos')
                            ->where('id_plataforma', $id_plataforma);
                    })
                    ->update([
                        'estatus' => 'identidad_validada',
                        'fecha_validacion' => now()->setTimezone('America/Mexico_City'),
                    ]);

                BitacoraEventos::create([
                    'evento' => 'Canje validado directamente',
                    'descripcion' => "El usuario con id: {$user->id} validó el canje: {$request->id} sin proveedor",
                    'id_usuario' => $user->id,
                ]);
            });
        } catch (\Throwable $th) {
            return $this->sendError('Error al validar', $th->getMessage(), 500);
        }

        $this->notificarValidacion($request, $id_plataforma, true);

        return $this->sendResponse('Validación realizada exitosamente.');
    }
    /** Envía WhatsApp y correo solo con los productos validados. Un fallo aquí no revierte la validación. */
    private function notificarValidacion(Request $request, int $id_plataforma, ?bool $directo): void
    {
        try {
            $canje = $this->obtenerCanje($request->id, $request->plataforma === 'club_bohn');
            if (!$canje) {
                return;
            }

            $canje = $this->soloProductosValidados(
                $canje,
                collect($request->productos)->pluck('id_producto')->all()
            );

            if ($canje->productos->isEmpty()) {
                Log::warning("Canje {$request->id}: ningún producto validado coincidió para notificar");
                return;
            }

            $this->enviarWhatsApp($canje, null, $directo, null, $id_plataforma);
            $this->enviarCorreo($canje, null, $directo, null, $id_plataforma);
        } catch (\Throwable $th) {
            Log::error("Error al notificar la validación del canje {$request->id}: " . $th->getMessage());
        }
    }

    private function enviarWhatsApp($canje, $codigo = null, $validado = null, $proveedor = null, $plataforma = null)
    {
        try {
            //conseguir los telefonos de todos los administradores
            $telefonosAdmins = UsuariosPlataforma::where('tipo_usuario', 7)
                ->whereNotNull('telefono')
                ->where('telefono', '!=', '')
                ->pluck('telefono')
                ->toArray();

            $canjeIdEncriptado = $this->encriptarCorto($canje->id);
            $plataformaDatos = Plataformas::find($plataforma);
            if (!$plataformaDatos) {
                Log::error("Correo: no se encontró la plataforma con id '{$plataforma}'");
                return;
            }
            $idPlataforma = $plataformaDatos->id;
            $nombrePlataforma = $plataformaDatos->nombre;


            $urlva = config('app.url_frontend') . "/validar-canje/{$canjeIdEncriptado}/{$idPlataforma}";

            $listaPremios = collect($canje->productos)
                ->map(fn($p) => "• {$p->nombre} (x{$p->cantidad})")
                ->implode("\n");

            if ($codigo) {
                $titulo = "🔐 Código de verificación de identidad 🔐";
                $mensaje = "Ha solicitado un código para verificar su identidad, ingresa a la web y coloca el siguiente código para verificarte:\n\n" .
                    "🔐 Código: *{$codigo}*\n\n" .
                    "🔗 Link hacia la web:\n{$urlva}\n\n";

                $this->whatsappService->sendMessage($canje->phone, $titulo);
                $this->whatsappService->sendMessage($canje->phone, $mensaje);
            } else if ($validado) {
                $titulo = "✅ Identidad validada correctamente ✅";
                $mensaje = "Se ha validado la identidad de un canje, los datos validados corresponden a:\n\n" .
                    "👤 Cliente: {$canje->name}\n" .
                    "📧 Correo: {$canje->email}\n" .
                    "📱 Teléfono: {$canje->phone}\n" .
                    "🎁 Premios:\n{$listaPremios}\n" .
                    "📄 Folio: {$canje->folio}\n" .
                    "💻 Plataforma: {$nombrePlataforma}\n";

                foreach ($telefonosAdmins as $telefono) {
                    $this->whatsappService->sendMessage($telefono, $titulo);
                    $this->whatsappService->sendMessage($telefono, $mensaje);
                }
            } else {
                $titulo = "🔔 Nueva solicitud de validación de identidad 🔔";
                $mensaje = "📋 *Detalles del canje:*\n\n" .
                    "👤 Cliente: {$canje->name}\n" .
                    "📧 Correo: {$canje->email}\n" .
                    "📱 Teléfono: {$canje->phone}\n" .
                    "🎁 Premios:\n{$listaPremios}\n" .
                    "📄 Folio: {$canje->folio}\n" .
                    "🔗 Link para validación:\n{$urlva}\n\n" .
                    "💻 Plataforma: {$nombrePlataforma}\n" .
                    "✅ Por favor, proceder con la validación de identidad.";

                $this->whatsappService->sendMessage($canje->phone, $titulo);
                $this->whatsappService->sendMessage($canje->phone, $mensaje);
            }

            return $this->sendResponse('Validación enviada exitosamente.');
        } catch (\Throwable $th) {
            Log::error("Error al enviar WhatsApp: " . $th->getMessage());
        }
    }

    private function enviarCorreo($canje, $codigo = null, $validado = null, $proveedor = null, $plataforma = null)
    {
        try {
            $destinatarios = [
                'carrera.jorge@dirac.mx'
            ];
            //conseguir los correos de todos los administradores
            $correosAdmins = UsuariosPlataforma::where('tipo_usuario', 7)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->pluck('email')
                ->toArray();

            $canjeData = (object) [
                'folio' => $canje->folio,
                'nombre_usuario' => $canje->name,
                'email' => $canje->email,
                'phone' => $canje->phone,
                'nombre_premio' => $canje->premios,
                'puntos_canjeados' => $canje->puntos_total,
                'productos' => $canje->productos,
                'calle' => $canje->street,
                'numero_calle' => $canje->number,
                'colonia' => $canje->colony,
                'municipio' => $canje->municipality,
                'codigo_postal' => $canje->postal_code,
            ];

            $canjeIdEncriptado = $this->encriptarCorto($canje->id);
            $plataformaDatos = Plataformas::find($plataforma);
            if (!$plataformaDatos) {
                Log::error("Correo: no se encontró la plataforma con id '{$plataforma}'");
                return;
            }
            $idPlataforma = $plataformaDatos->id;

            $urlva = config('app.url_frontend') . "/validar-canje/{$canjeIdEncriptado}/{$idPlataforma}";

            if ($codigo) {
                Mail::to($canjeData->email)->send(new SolicitarCodigo($canjeData, $codigo, $urlva));
            } else if ($validado) {
                foreach ($correosAdmins as $correo) {
                    Mail::to($correo)->send(new IdentidadValidada($canjeData, $codigo, $urlva));
                }
            } else {
                Mail::to($canjeData->email)->send(new ValidacionCanjeEnviada($canjeData, $codigo, $urlva));
            }

            Log::info("Correo enviado correctamente");
        } catch (\Exception $e) {
            Log::error("Error al enviar correo: " . $e->getMessage());
        }
    }
}
