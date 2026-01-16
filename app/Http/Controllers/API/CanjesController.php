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
use App\Models\CatalogoCategoria;
use App\Models\CatalogoProductos;
use App\Models\CatalogoProveedores;
use App\Models\ValidacionCanje;
use App\Mail\ValidacionCanjeEnviada;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Crypt;

class CanjesController extends BaseController
{
    protected $whatsappService;

    public function __construct()
    {
        $this->whatsappService = new WhatsAppService();
    }

    public function validarCanje(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'nombre_producto' => 'required|string',
                'descripcion' => 'required|string',
                'marca' => 'required|string',
                'sku' => 'required|string',
                'color' => 'required|string',
                'costo_con_iva' => 'required|integer',
                'costo_sin_iva' => 'required|integer',
                'costo_puntos_con_iva' => 'required|integer',
                'costo_puntos_sin_iva' => 'required|integer',
                'fee_brimagy' => 'required|integer',
                'subtotal' => 'required|integer',
                'envio_base' => 'required|integer',
                'costo_caja' => 'required|integer',
                'envio_extra' => 'required|integer',
                'total_envio' => 'required|integer',
                'total' => 'required|integer',
                'puntos' => 'required|integer',
                'factor' => 'required|integer',
                'tipo_registro' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $id_proveedor = $request->id_proveedor;
            $id_catalogo = $request->id_catalogo;

            if ($request->tipo_registro === 'excel') {
                // Buscar proveedor por nombre
                $proveedor = CatalogoProveedores::where('nombre', 'like', '%' . $request->proveedor . '%')->first();
                if (!$proveedor) {
                    DB::rollBack();
                    return $this->sendError('El proveedor "' . $request->proveedor . '" no existe', 'error', 404);
                }
                $id_proveedor = $proveedor->id;

                // Buscar categoría por nombre
                $catalogo = CatalogoCategoria::where('desc', 'like', '%' . $request->catalogo . '%')->first();
                if (!$catalogo) {
                    DB::rollBack();
                    return $this->sendError('La categoría "' . $request->catalogo . '" no existe', 'error', 404);
                }
                $id_catalogo = $catalogo->id;
            }

            // Verificar si ya existe un producto con ese SKU
            $productoExistente = CatalogoProductos::where('sku', $request->sku)->first();

            if ($productoExistente) {
                // Si existe, actualizarlo
                $productoExistente->update([
                    'nombre_producto' => $request->nombre_producto,
                    'descripcion' => $request->descripcion,
                    'marca' => $request->marca,
                    'color' => $request->color,
                    'id_proveedor' => $id_proveedor,
                    'id_catalogo' => $id_catalogo,
                    'costo_con_iva' => $request->costo_con_iva,
                    'costo_sin_iva' => $request->costo_sin_iva,
                    'costo_puntos_con_iva' => $request->costo_puntos_con_iva,
                    'costo_puntos_sin_iva' => $request->costo_puntos_sin_iva,
                    'fee_brimagy' => $request->fee_brimagy,
                    'subtotal' => $request->subtotal,
                    'envio_base' => $request->envio_base,
                    'costo_caja' => $request->costo_caja,
                    'envio_extra' => $request->envio_extra,
                    'total_envio' => $request->total_envio,
                    'total' => $request->total,
                    'puntos' => $request->puntos,
                    'factor' => $request->factor,
                    'updated_at' => now()->setTimezone('America/Mexico_City'),
                ]);

                $user = Auth::user();
                $log['evento'] = 'Actualización de producto';
                $log['descripcion'] = "El usuario con id: {$user->id} actualizó el producto con id: {$productoExistente->id} (SKU: {$request->sku})";
                $log['id_usuario'] = $user->id;
                BitacoraEventos::create($log);

                DB::commit();

                return $this->sendResponse($productoExistente, 'Producto actualizado exitosamente.');
            }

            $producto = CatalogoProductos::create([
                'nombre_producto' => $request->nombre_producto,
                'descripcion' => $request->descripcion,
                'marca' => $request->marca,
                'sku' => $request->sku,
                'color' => $request->color,
                'id_proveedor' => $id_proveedor,
                'id_catalogo' => $id_catalogo,
                'costo_con_iva' => $request->costo_con_iva,
                'costo_sin_iva' => $request->costo_sin_iva,
                'costo_puntos_con_iva' => $request->costo_puntos_con_iva,
                'costo_puntos_sin_iva' => $request->costo_puntos_sin_iva,
                'fee_brimagy' => $request->fee_brimagy,
                'subtotal' => $request->subtotal,
                'envio_base' => $request->envio_base,
                'costo_caja' => $request->costo_caja,
                'envio_extra' => $request->envio_extra,
                'total_envio' => $request->total_envio,
                'total' => $request->total,
                'puntos' => $request->puntos,
                'factor' => $request->factor,
            ]);

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

            $user = Auth::user();
            $validacion = ValidacionCanje::create([
                'id_canje' => $request->id,
                'id_usuario_admin' => $user->id,
            ]);

            $canje = DB::table('swaps_view')
                ->where('id', $request->id)
                ->first();

            if ($canje) {
                $this->enviarWhatsApp($canje);
                $this->enviarCorreo($canje);
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
    private function enviarWhatsApp($canje, $codigo = null, $validado = null)
    {
        try {
            //conseguir los telefonos de todos los administradores
            $telefonosAdmins = User::where('tipo_usuario', 6)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->pluck('phone')
                ->toArray();

            $canjeIdEncriptado = $this->encriptarCorto($canje->id);
            $urlva = env('APP_FRONT_URL') . "/validar-canje/{$canjeIdEncriptado}";

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
                    "📄 Folio: {$canje->folio}\n";

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
                    "✅ Por favor, proceder con la validación de identidad.";

                $this->whatsappService->sendMessage($canje->phone, $titulo);
                $this->whatsappService->sendMessage($canje->phone, $mensaje);
            }

            return $this->sendResponse('Validación enviada exitosamente.');
        } catch (\Throwable $th) {
            return $this->sendError('Error al enviar la validación', $th->getMessage(), 500);
        }
    }

    private function enviarCorreo($canje, $codigo = null, $validado = null)
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

            $canjeData = (object)[
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

            $canjeIdEncriptado = $this->encriptarCorto($canje->id); //Crypt::encryptString($canje->id)
            $urlva = env('APP_FRONT_URL') . "/validar-canje/{$canjeIdEncriptado}";

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

    public function getCanjes(Request $request)
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
                    DB::raw('(SELECT vc.estatus FROM dc_validacion_canje vc WHERE vc.id_canje = sp.id LIMIT 1) as estado_validacion')
                );

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('sp.folio', 'LIKE', "%{$search}%")
                        ->orWhere('sp.name', 'LIKE', "%{$search}%")
                        ->orWhere('sp.email', 'LIKE', "%{$search}%")
                        ->orWhere('sp.sku', 'LIKE', "%{$search}%")
                        ->orWhere('sp.points_swap', 'LIKE', "%{$search}%")
                        ->orWhere('vc.estatus', 'LIKE', "%{$search}%");
                });
            }

            $canjes = $query->orderBy('sp.created_at', 'desc')->get();

            return $this->sendResponse($canjes);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los canjes', $th, 500);
        }
    }

    public function getCanjeById(Request $request)
    {
        try {
            $idCanjeDesencriptado = $this->desencriptarCorto($request->id_canje);

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

            $canje = DB::table('swaps_view')
                ->where('id', $request->id)
                ->first();

            if ($canje) {
                $this->enviarWhatsApp($canje, $codigoValidacion);
                $this->enviarCorreo($canje, $codigoValidacion);
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
}
