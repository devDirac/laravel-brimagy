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
use App\Models\User;
use App\Models\UsuariosPlataforma;
use App\Services\WhatsAppService;
use Carbon\Carbon;

class CanjesController extends BaseController
{
    protected $whatsappService;

    public function __construct()
    {
        $this->whatsappService = new WhatsAppService();
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
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $plataforma = $request->plataforma === 'club_bohn' ? 'club bohn' : $request->plataforma;
            // es club bohn
            $plataformaModel = Plataformas::where('nombre', $plataforma)->first();
            if (!$plataformaModel) {
                return $this->sendError('La plataforma ' . $request->plataforma . ' no existe', 'error', 404);
            }
            $id_plataforma = $plataformaModel->id;

            $user = Auth::user();
            $validacion = ValidacionCanje::create([
                'id_canje' => $request->id,
                'id_producto' => $request->id_producto,
                'cantidad_producto' => $request->number_of_awards,
                'id_proveedor' => $request->id_proveedor,
                'id_usuario_admin' => $user->id,
                'id_plataforma' => $id_plataforma
            ]);

            if ($request->has('plataforma') && $request->plataforma === "club_bohn") {
                $canje = DB::connection('mysql_club_bohn')
                    ->table('swaps_view')
                    ->where('id', $request->id)
                    ->first();
            } else {
                $canje = DB::table('swaps_view')
                    ->where('id', $request->id)
                    ->first();
            }

            if ($canje) {
                $this->enviarWhatsApp($canje, null, null, null, $plataforma);
                $this->enviarCorreo($canje, null, null, null);
            }

            $log['evento'] = 'Creación de validación de cliente';
            $log['descripcion'] = "El usuario con id: {$user->id} envió un mensaje para validar el canje: {$canje->folio}";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($validacion, 'Validación enviada exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al enviar la validación', $th->getMessage(), 500);
        }
    }

    private function generarCodigoUnico()
    {
        $codigo = random_int(100000, 999999);
        return $codigo;
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
            $plataformaEncriptada = $this->encriptarCorto($plataforma);
            $urlva = config('app.url_frontend') . "/validar-canje/{$canjeIdEncriptado}/{$plataformaEncriptada}";

            if ($codigo) {
                $titulo = "🔐 Código de verificación de identidad 🔐";
                $mensaje = "Ha solicitado un código para verificar su identidad, ingresa a la web y coloca el siguiente código para verificarte:\n\n" .
                    "🔐 Código: *{$codigo}*\n\n" .
                    "🔗 Link hacia la web:\n{$urlva}\n\n";

                $this->whatsappService->sendMessage($canje->phone, $titulo);
                $this->whatsappService->sendMessage($canje->phone, $mensaje);
            } else if ($validado) {
                $titulo = "✅ Identidad validada correctamente ✅";
                $mensaje = "Se ha validado la identidad de un canje por parte de un cliente, los datos validados corresponden a:\n\n" .
                    "👤 Cliente: {$canje->name}\n" .
                    "📧 Correo: {$canje->email}\n" .
                    "📱 Teléfono: {$canje->phone}\n" .
                    "🎁 Premio: {$canje->desc}\n" .
                    "📄 Folio: {$canje->folio}\n" .
                    "💻 Plataforma: {$plataforma}\n";

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
                    "🎁 Premio: {$canje->desc}\n" .
                    "📄 Folio: {$canje->folio}\n" .
                    "🔗 Link para validación:\n{$urlva}\n\n" .
                    "💻 Plataforma: {$plataforma}\n" .
                    "✅ Por favor, proceder con la validación de identidad.";

                $this->whatsappService->sendMessage($canje->phone, $titulo);
                $this->whatsappService->sendMessage($canje->phone, $mensaje);
            }

            return $this->sendResponse('Validación enviada exitosamente.');
        } catch (\Throwable $th) {
            return $this->sendError('Error al enviar la validación', $th->getMessage(), 500);
        }
    }

    private function enviarCorreo($canje, $codigo = null, $validado = null, $proveedor = null, $plataforma = null)
    {
        try {
            $destinatarios = [
                'carrera.jorge@dirac.mx'
            ];
            //conseguir los correos de todos los administradores
            $correosAdmins = User::where('tipo_usuario', 6)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->pluck('email')
                ->toArray();

            $canjeData = (object) [
                'folio' => $canje->folio,
                'nombre_usuario' => $canje->name,
                'email' => $canje->email,
                'phone' => $canje->phone,
                'nombre_premio' => $canje->desc,
                'puntos_canjeados' => $canje->points_swap,
                'calle' => $canje->street,
                'numero_calle' => $canje->number,
                'colonia' => $canje->colony,
                'municipio' => $canje->municipality,
                'codigo_postal' => $canje->postal_code,
            ];

            $canjeIdEncriptado = $this->encriptarCorto($canje->id);
            $plataformaEncriptada = $this->encriptarCorto($plataforma);
            $urlva = config('app.url_frontend') . "/validar-canje/{$canjeIdEncriptado}/{$plataformaEncriptada}";
            //$urlva = config('app.url_frontend') . "/validar-canje/{$canjeIdEncriptado}";

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

    /*public function getCanjes(Request $request)
    {
        try {
            $query = DB::table('swaps_view as sp')
                ->leftJoin('dc_validacion_canje as vc', function ($join) {
                    $join->on('vc.id_canje', '=', 'sp.id')
                        ->whereRaw('vc.id = (SELECT MAX(id) FROM dc_validacion_canje WHERE id_canje = sp.id)');
                })
                ->select(
                    'sp.id',
                    'sp.folio',
                    'sp.name as nombre_usuario',
                    'sp.email',
                    'sp.phone',
                    'sp.number_of_awards',
                    'sp.size',
                    'sp.color',
                    'sp.category',
                    'sp.points_swap as puntos_canjeados',
                    'sp.desc as nombre_premio',
                    'sp.required_score as costo_premio',
                    'sp.sku',
                    'sp.street as calle',
                    'sp.number as numero_calle',
                    'sp.colony as colonia',
                    'sp.postal_code as codigo_postal',
                    'sp.municipality as municipio',
                    'sp.inside as numero_interior',
                    'sp.between_1',
                    'sp.between_2',
                    'sp.additional_reference as referencia_adicional',
                    'sp.created_at as creacion_canje',
                    'sp.status as estado_canje',
                    'cdp.id as id_producto',
                    'cdp.id_proveedor',
                    'cdp.sku as sku_catalogo',
                    DB::raw('(SELECT vc.estatus FROM dc_validacion_canje vc WHERE vc.id_canje = sp.id LIMIT 1) as estado_validacion')
                )
                ->leftJoin('dc_catalogo_productos as cdp', 'sp.award_id', '=', 'cdp.id_producto_brimagy')
                ->where('cdp.tipo_producto', $request->tipo_producto);

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('sp.folio', 'LIKE', "%{$search}%")
                        ->orWhere('sp.desc', 'LIKE', "%{$search}%")
                        ->orWhere('sp.email', 'LIKE', "%{$search}%")
                        ->orWhere('sp.sku', 'LIKE', "%{$search}%")
                        ->orWhere('sp.points_swap', 'LIKE', "%{$search}%")
                        ->orWhere('vc.estatus', 'LIKE', "%{$search}%");
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

                $query->whereBetween('sp.created_at', [$inicio, $fin]);
            }

            // PLATAFORMA
            if ($request->has('plataforma') && !empty($request->plataforma)) {

                // Buscar plataforma por nombre
                $plataforma = $request->plataforma === 'club_bohn' ? 'club bohn' : $request->plataforma;
                $plataformaModel = Plataformas::where('nombre', $plataforma)->first();
                if (!$plataformaModel) {
                    return $this->sendError('La plataforma "' . $plataforma . '" no existe', 'error', 404);
                }
                $id_plataforma = $plataformaModel->id;

                $query->where(function ($q) use ($id_plataforma) {
                    $q->where('cdp.id_plataforma', $id_plataforma);
                });
            }

            $canjes = $query->orderBy('sp.created_at', 'desc')->get();

            return $this->sendResponse($canjes);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los canjes', $th, 500);
        }
    }*/
    public function getCanjes(Request $request)
    {
        try {

            if ($request->has('plataforma') && $request->plataforma === "club_bohn") {
                return $this->getCanjesClubBohn($request);
            }

            //puntotes
            $query = DB::table('swaps_view as sp')
                ->leftJoin('dc_validacion_canje as vc', function ($join) {
                    $join->on('vc.id_canje', '=', 'sp.id')
                        ->whereRaw('vc.id = (SELECT MAX(id) FROM dc_validacion_canje WHERE id_canje = sp.id)');
                })
                ->leftJoin('dc_catalogo_productos as cdp', 'sp.award_id', '=', 'cdp.id_producto_brimagy')
                ->select(
                    'sp.id',
                    'sp.folio',
                    'sp.name as nombre_usuario',
                    'sp.email',
                    'sp.phone',
                    'sp.number_of_awards',
                    'sp.size',
                    'sp.color',
                    'sp.category',
                    'sp.points_swap as puntos_canjeados',
                    'sp.desc as nombre_premio',
                    'sp.required_score as costo_premio',
                    'sp.sku',
                    'sp.street as calle',
                    'sp.number as numero_calle',
                    'sp.colony as colonia',
                    'sp.postal_code as codigo_postal',
                    'sp.municipality as municipio',
                    'sp.inside as numero_interior',
                    'sp.between_1',
                    'sp.between_2',
                    'sp.additional_reference as referencia_adicional',
                    'sp.created_at as creacion_canje',
                    'sp.status as estado_canje',
                    'cdp.id as id_producto',
                    'cdp.id_proveedor',
                    'cdp.sku as sku_catalogo',
                    DB::raw('(SELECT vc.estatus FROM dc_validacion_canje vc WHERE vc.id_canje = sp.id LIMIT 1) as estado_validacion')
                )
                ->where('cdp.tipo_producto', $request->tipo_producto);

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('sp.folio', 'LIKE', "%{$search}%")
                        ->orWhere('sp.desc', 'LIKE', "%{$search}%")
                        ->orWhere('sp.email', 'LIKE', "%{$search}%")
                        ->orWhere('sp.sku', 'LIKE', "%{$search}%")
                        ->orWhere('sp.points_swap', 'LIKE', "%{$search}%")
                        ->orWhere('vc.estatus', 'LIKE', "%{$search}%");
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

                $query->whereBetween('sp.created_at', [$inicio, $fin]);
            }

            $canjes = $query->orderBy('sp.created_at', 'desc')->get();

            return $this->sendResponse($canjes);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los canjes', $th, 500);
        }
    }
    private function getCanjesClubBohn(Request $request)
    {
        try {
            // es club bohn
            $plataformaModel = Plataformas::where('nombre', 'club bohn')->first();
            if (!$plataformaModel) {
                return $this->sendError('La plataforma "club bohn" no existe', 'error', 404);
            }
            $id_plataforma = $plataformaModel->id;

            $query = DB::connection('mysql_club_bohn')
                ->table('swaps_view as sp')
                ->select(
                    'sp.id',
                    'sp.folio',
                    'sp.name as nombre_usuario',
                    'sp.email',
                    'sp.phone',
                    'sp.number_of_awards',
                    'sp.size',
                    'sp.color',
                    'sp.category',
                    'sp.points_swap as puntos_canjeados',
                    'sp.desc as nombre_premio',
                    'sp.required_score as costo_premio',
                    'sp.sku',
                    'sp.street as calle',
                    'sp.number as numero_calle',
                    'sp.colony as colonia',
                    'sp.postal_code as codigo_postal',
                    'sp.municipality as municipio',
                    'sp.inside as numero_interior',
                    'sp.between_1',
                    'sp.between_2',
                    'sp.additional_reference as referencia_adicional',
                    'sp.created_at as creacion_canje',
                    'sp.status as estado_canje',
                    'sp.award_id'
                );

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('sp.folio', 'LIKE', "%{$search}%")
                        ->orWhere('sp.desc', 'LIKE', "%{$search}%")
                        ->orWhere('sp.email', 'LIKE', "%{$search}%")
                        ->orWhere('sp.sku', 'LIKE', "%{$search}%")
                        ->orWhere('sp.points_swap', 'LIKE', "%{$search}%");
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

                $query->whereBetween('sp.created_at', [$inicio, $fin]);
            }

            $swaps = $query->orderBy('sp.created_at', 'desc')->get();

            if ($swaps->isEmpty()) {
                return $this->sendResponse(collect());
            }

            $awardIds = $swaps->pluck('award_id')->filter()->unique()->values();

            $catalogo = DB::table('dc_catalogo_productos as cdp')
                ->where('cdp.id_plataforma', $id_plataforma)
                ->where('cdp.tipo_producto', $request->tipo_producto)
                ->whereIn('cdp.id_producto_brimagy', $awardIds)
                ->select('cdp.id', 'cdp.id_proveedor', 'cdp.sku', 'cdp.id_producto_brimagy')
                ->get()
                ->keyBy('id_producto_brimagy');

            $swapIds = $swaps->pluck('id')->unique()->values();

            $validaciones = DB::table('dc_validacion_canje')
                ->whereIn('id_canje', $swapIds)
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('id_canje')
                ->map(fn($grupo) => $grupo->first());

            $canjes = $swaps
                ->map(function ($swap) use ($catalogo, $validaciones) {
                    $producto = $catalogo->get($swap->award_id);

                    if (!$producto) {
                        return null;
                    }

                    $validacion = $validaciones->get($swap->id);

                    return (object) array_merge((array) $swap, [
                        'id_producto' => $producto->id,
                        'id_proveedor' => $producto->id_proveedor,
                        'sku_catalogo' => $producto->sku,
                        'estado_validacion' => $validacion->estatus ?? null,
                    ]);
                })
                ->filter()
                ->values();

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $yaCoincidePorOtroCampo = $canjes->contains(function ($c) use ($search) {
                    return stripos($c->folio, $search) !== false
                        || stripos($c->nombre_premio, $search) !== false
                        || stripos($c->email, $search) !== false
                        || stripos($c->sku, $search) !== false
                        || stripos((string) $c->puntos_canjeados, $search) !== false;
                });

                if (!$yaCoincidePorOtroCampo) {
                    $canjes = $canjes->filter(fn($c) => stripos((string) $c->estado_validacion, $search) !== false)
                        ->values();
                }
            }

            return $this->sendResponse($canjes);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los canjes de club bohn', $th, 500);
        }
    }
    public function getCanjeById(Request $request)
    {
        try {
            $idCanjeDesencriptado = $this->desencriptarCorto($request->id_canje);
            $idPlataformaDesencriptado = $this->desencriptarCorto($request->plataforma);

            $plataformaModel = Plataformas::where('nombre', $idPlataformaDesencriptado)->first();
            if (!$plataformaModel) {
                return $this->sendError('La plataforma no existe', null, 404);
            }

            $esClubBohn = $plataformaModel->nombre === 'club bohn';

            if ($esClubBohn) {
                $canje = DB::connection('mysql_club_bohn')
                    ->table('swaps_view as sp')
                    ->select(
                        'sp.id',
                        'sp.folio',
                        'sp.name as nombre_usuario',
                        'sp.email',
                        'sp.phone',
                        'sp.number_of_awards',
                        'sp.size',
                        'sp.color',
                        'sp.category',
                        'sp.points_swap as puntos_canjeados',
                        'sp.desc as nombre_premio',
                        'sp.required_score as costo_premio',
                        'sp.sku',
                        'sp.street as calle',
                        'sp.number as numero_calle',
                        'sp.colony as colonia',
                        'sp.postal_code as codigo_postal',
                        'sp.municipality as municipio',
                        'sp.inside as numero_interior',
                        'sp.between_1',
                        'sp.between_2',
                        'sp.additional_reference as referencia_adicional',
                        'sp.created_at as creacion_canje',
                        'sp.status as estado_canje'
                    )
                    ->where('sp.id', $idCanjeDesencriptado)
                    ->first();

                if ($canje) {
                    $validacion = DB::table('dc_validacion_canje')
                        ->where('id_canje', $idCanjeDesencriptado)
                        ->orderBy('id', 'desc')
                        ->first();

                    $canje = (object) array_merge((array) $canje, [
                        'estado_validacion' => $validacion->estatus ?? null,
                        'codigo_validacion' => $validacion->codigo_validacion ?? null,
                    ]);
                }
            } else {
                $canje = DB::table('swaps_view as sp')
                    ->select(
                        'sp.id',
                        'sp.folio',
                        'sp.name as nombre_usuario',
                        'sp.email',
                        'sp.phone',
                        'sp.number_of_awards',
                        'sp.size',
                        'sp.color',
                        'sp.category',
                        'sp.points_swap as puntos_canjeados',
                        'sp.desc as nombre_premio',
                        'sp.required_score as costo_premio',
                        'sp.sku',
                        'sp.street as calle',
                        'sp.number as numero_calle',
                        'sp.colony as colonia',
                        'sp.postal_code as codigo_postal',
                        'sp.municipality as municipio',
                        'sp.inside as numero_interior',
                        'sp.between_1',
                        'sp.between_2',
                        'sp.additional_reference as referencia_adicional',
                        'sp.created_at as creacion_canje',
                        'sp.status as estado_canje',
                        'vc.estatus as estado_validacion',
                        'vc.codigo_validacion'
                    )
                    ->leftJoin('dc_validacion_canje as vc', 'vc.id_canje', '=', 'sp.id')
                    ->where('sp.id', $idCanjeDesencriptado)
                    ->first();
            }

            if (!$canje) {
                return $this->sendError('Canje no encontrado', null, 404);
            }

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

            $idPlataformaDesencriptado = $this->desencriptarCorto($request->plataforma);

            $plataformaModel = Plataformas::where('nombre', $idPlataformaDesencriptado)->first();
            if (!$plataformaModel) {
                return $this->sendError('La plataforma no existe', null, 404);
            }

            $esClubBohn = $plataformaModel->nombre === 'club bohn';
            $plataforma = $plataformaModel->nombre;

            $validacionExistente = ValidacionCanje::where('id_canje', $request->id)->first();

            if ($validacionExistente && !empty($validacionExistente->visible)) {
                DB::commit();
                return $this->sendResponse($validacionExistente, 'Código de validación existente recuperado.');
            }

            $codigoValidacion = $this->generarCodigoUnico();

            $validacion = ValidacionCanje::updateOrCreate(
                ['id_canje' => $request->id],
                [
                    'codigo_validacion' => $codigoValidacion,
                    'estatus' => 'solicitud_enviada'
                ]
            );

            if ($esClubBohn) {
                $canje = DB::connection('mysql_club_bohn')
                    ->table('swaps_view')
                    ->where('id', $request->id)
                    ->first();
            } else {
                $canje = DB::table('swaps_view')
                    ->where('id', $request->id)
                    ->first();
            }

            if ($canje) {
                $this->enviarWhatsApp($canje, $codigoValidacion, null, null, $plataforma);
                $this->enviarCorreo($canje, $codigoValidacion, null, null, $plataforma);
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
            $validarCanjePorCodigo = ValidacionCanje::where('id_canje', $request->id_canje)
                ->where('codigo_validacion', $request->codigo)
                ->first();

            if (!$validarCanjePorCodigo) {
                return $this->sendError('No coincide el código de validación', null, 500);
            } else {
                $validacion = ValidacionCanje::where('id_canje', $request->id_canje)
                    ->update([
                        'estatus' => "identidad_validada",
                        'fecha_validacion' => now()->setTimezone('America/Mexico_City'),
                    ]);
            }

            $canje = DB::table('swaps_view')
                ->where('id', $request->id_canje)
                ->first();
            $validado = true;

            if ($validacion) {
                $this->enviarWhatsApp($canje, null, $validado);
                $this->enviarCorreo($canje, null, $validado);
            }

            return $this->sendResponse($validacion, 'Canje validado correctamente');
        } catch (\Throwable $th) {
            return $this->sendError('Error al validar el canje', $th->getMessage(), 500);
        }
    }

    public function enviarValidacionSinProveedor(Request $request)
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

            $user = Auth::user();
            $validacion = ValidacionCanje::create([
                'id_canje' => $request->id,
                'id_producto' => $request->id_producto,
                'cantidad_producto' => $request->number_of_awards,
                'id_proveedor' => $request->id_proveedor,
                'id_usuario_admin' => $user->id,
                'estatus' => 'identidad_validada',
                'fecha_validacion' => now()->setTimezone('America/Mexico_City'),
            ]);

            $plataforma = $request->plataforma === "club_bohn" ? "club bohn" : "puntotes";

            if ($request->has('plataforma') && $request->plataforma === "club_bohn") {
                $canje = DB::connection('mysql_club_bohn')
                    ->table('swaps_view')
                    ->where('id', $request->id)
                    ->first();
            } else {
                $canje = DB::table('swaps_view')
                    ->where('id', $request->id)
                    ->first();
            }

            $validado = true;

            if ($validacion) {
                $this->enviarWhatsApp($canje, null, $validado, null, $plataforma);
                $this->enviarCorreo($canje, null, $validado, null);
            }
            $log['evento'] = 'Canje validado directamente';
            $log['descripcion'] = "El usuario con id: {$user->id} validó el canje: {$canje->folio} sin proveedor";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($validacion, 'Validación realizada exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al validar', $th->getMessage(), 500);
        }
    }
}
