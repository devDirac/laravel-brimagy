<?php

use App\Http\Controllers\API\AlmacenController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CanjesController;
use App\Http\Controllers\API\CategoriasController;
use App\Http\Controllers\API\ConfiguracionesController;
use App\Http\Controllers\API\EncuestasController;
use App\Http\Controllers\API\EvidenciasController;
use App\Http\Controllers\API\FacturasController;
use App\Http\Controllers\API\NotificacionesController;
use App\Http\Controllers\API\OrdenCompraController;
use App\Http\Controllers\API\ProductosController;
use App\Http\Controllers\API\ProveedorController;
use App\Http\Controllers\API\UserController;

$SANCTUM = 'auth:sanctum';

Route::post('login', [AuthController::class, 'signin']);
Route::post('logOut', [AuthController::class, 'logOut'])->middleware($SANCTUM);
Route::post('register', [AuthController::class, 'signup'])->middleware($SANCTUM);

Route::get('getCheckUsuario', [UserController::class, 'getCheckUsuarioHttp'])->middleware($SANCTUM);
Route::get('getCheckEmail', [UserController::class, 'getCheckEmailHttp'])->middleware($SANCTUM);
Route::get('getUsuarios', [UserController::class, 'getUsuarios'])->middleware($SANCTUM);
Route::put('activarUsuario', [UserController::class, 'activarUsuario'])->middleware($SANCTUM);
Route::put('desactivarUsuario', [UserController::class, 'desactivarUsuario'])->middleware($SANCTUM);
Route::put('editarUsuario', [UserController::class, 'editarUsuario'])->middleware($SANCTUM);
Route::get('getUsuarioPorId', [UserController::class, 'getUsuarioPorId'])->middleware($SANCTUM);

Route::post('recuperaContrasena', [AuthController::class, 'passwordRecoverSendLink']);
Route::post('recuperaContrasenaTokenValidacion', [AuthController::class, 'passwordRecoverTokenValidation']);
Route::post('actualizacionContrasena', [AuthController::class, 'passwordReset']);

//PROVEEDORES
Route::post('crearProveedor', [ProveedorController::class, 'crearProveedor'])->middleware($SANCTUM);
Route::get('getProveedores', [ProveedorController::class, 'getProveedores'])->middleware($SANCTUM);
Route::put('editarProveedor', [ProveedorController::class, 'editarProveedor'])->middleware($SANCTUM);
Route::delete('eliminarProveedor', [ProveedorController::class, 'eliminarProveedor'])->middleware($SANCTUM);
//CATEGORIAS
Route::post('crearCategoria', [CategoriasController::class, 'crearCategoria'])->middleware($SANCTUM);
Route::get('getCategorias', [CategoriasController::class, 'getCategorias'])->middleware($SANCTUM);
Route::put('editarCategoria', [CategoriasController::class, 'editarCategoria'])->middleware($SANCTUM);
Route::delete('eliminarCategoria', [CategoriasController::class, 'eliminarCategoria'])->middleware($SANCTUM);
//CATALOGO PRODUCTOS
Route::post('crearProducto', [ProductosController::class, 'crearProducto'])->middleware($SANCTUM);
Route::get('getCatalogoProductos', [ProductosController::class, 'getCatalogoProductos'])->middleware($SANCTUM);
Route::get('getCatalogoProductosFisicos', [ProductosController::class, 'getCatalogoProductosFisicos'])->middleware($SANCTUM);
Route::get('getCatalogoProductosDigitales', [ProductosController::class, 'getCatalogoProductosDigitales'])->middleware($SANCTUM);
Route::put('editarProducto', [ProductosController::class, 'editarProducto'])->middleware($SANCTUM);
Route::delete('eliminarProducto', [ProductosController::class, 'eliminarProducto'])->middleware($SANCTUM);
Route::post('verificarSkus', [ProductosController::class, 'verificarSkus'])->middleware($SANCTUM);
Route::post('verificarSkuDisponible', [ProductosController::class, 'verificarSkuDisponible'])->middleware($SANCTUM);
Route::get('busquedaInteligenteBrimagy', [ProductosController::class, 'busquedaInteligenteBrimagy'])->middleware($SANCTUM);
Route::get('getBitacoraProductoPorId', [ProductosController::class, 'getBitacoraProductoPorId'])->middleware($SANCTUM);

//CATALOGO CANJES
Route::get('getCanjes', [CanjesController::class, 'getCanjes'])->middleware($SANCTUM);

//VALIDACIÓN DE IDENTIDAD
Route::post('enviarValidacion', [CanjesController::class, 'enviarValidacion'])->middleware($SANCTUM);
Route::post('solicitarCodigoValidacion', [CanjesController::class, 'solicitarCodigoValidacion']);
Route::get('getCodigoVerificacionById', [CanjesController::class, 'getCodigoVerificacionById']);
Route::get('getCanjeById', [CanjesController::class, 'getCanjeById']);
Route::post('validarIdentidadPorCodigo', [CanjesController::class, 'validarIdentidadPorCodigo']);

//ORDEN DE COMPRA
Route::get('getOCPorId', [OrdenCompraController::class, 'getOCPorId'])->middleware($SANCTUM);
Route::get('getOCPorIdProveedor', [OrdenCompraController::class, 'getOCPorIdProveedor'])->middleware($SANCTUM);
Route::get('getCanjesPorProveedor', [OrdenCompraController::class, 'getCanjesPorProveedor'])->middleware($SANCTUM);
Route::get('getProveedoresOC', [OrdenCompraController::class, 'getProveedoresOC'])->middleware($SANCTUM);
Route::post('enviarCotizacionProveedor', [OrdenCompraController::class, 'enviarCotizacionProveedor'])->middleware($SANCTUM);
Route::get('getOrdenCompraPorProveedor', [OrdenCompraController::class, 'getOrdenCompraPorProveedor']);
Route::post('aceptarProductoOC', [OrdenCompraController::class, 'aceptarProductoOC']);
Route::post('rechazarProductoOC', [OrdenCompraController::class, 'rechazarProductoOC']);
Route::put('enviarOCAprobacion', [OrdenCompraController::class, 'enviarOCAprobacion']);
Route::put('enviarOrdenCompraFileProveedor', [OrdenCompraController::class, 'enviarOrdenCompraFileProveedor'])->middleware($SANCTUM);
Route::put('rechazarCotizacionDeProveedor', [OrdenCompraController::class, 'rechazarCotizacionDeProveedor'])->middleware($SANCTUM);

//FACTURAS XML Y PDF
Route::post('validarFacturaOrdenCompra', [OrdenCompraController::class, 'validarFacturaOrdenCompra']);
Route::post('subirPDFFactura', [OrdenCompraController::class, 'subirPDFFactura']);
Route::post('validarOrdenCompraFinal', [OrdenCompraController::class, 'validarOrdenCompraFinal']);
Route::put('addFechaPagoFactura', [FacturasController::class, 'addFechaPagoFactura'])->middleware($SANCTUM);

//MANDAR PRODUCTO A OTRO PROVEEDOR
Route::get('getProductoNuevoProveedor', [ProveedorController::class, 'getProductoNuevoProveedor'])->middleware($SANCTUM);
Route::put('enviarANuevoProveedor', [OrdenCompraController::class, 'enviarANuevoProveedor'])->middleware($SANCTUM);

//ALMACEN DE PRODUCTOS
Route::get('getProductosAlmacen', [AlmacenController::class, 'getProductosAlmacen'])->middleware($SANCTUM);
Route::get('getProductoAlmacenPorId', [AlmacenController::class, 'getProductoAlmacenPorId'])->middleware($SANCTUM);
Route::put('recibirProductoAlmacen', [AlmacenController::class, 'recibirProductoAlmacen'])->middleware($SANCTUM);
Route::put('addGuiaProductoAlmacen', [AlmacenController::class, 'addGuiaProductoAlmacen'])->middleware($SANCTUM);
Route::put('enviarProductoAlmacen', [AlmacenController::class, 'enviarProductoAlmacen'])->middleware($SANCTUM);
Route::put('confirmarRecepcionProductoAlmacen', [AlmacenController::class, 'confirmarRecepcionProductoAlmacen'])->middleware($SANCTUM);

//EVIDENCIAS DE ALMACEN DE PRODUCTOS
Route::post('subirEvidencias', [EvidenciasController::class, 'subirEvidencias'])->middleware($SANCTUM);

//ENCUESTAS
Route::get('getEncuestasDisponibles', [EncuestasController::class, 'getEncuestasDisponibles'])->middleware($SANCTUM);
Route::get('getPreguntasPorTipo', [EncuestasController::class, 'getPreguntasPorTipo'])->middleware($SANCTUM);
Route::post('createPreguntaEncuesta', [EncuestasController::class, 'createPreguntaEncuesta'])->middleware($SANCTUM);
Route::put('editarPreguntaEncuesta', [EncuestasController::class, 'editarPreguntaEncuesta'])->middleware($SANCTUM);
Route::put('desactivarPreguntaEncuesta', [EncuestasController::class, 'desactivarPreguntaEncuesta'])->middleware($SANCTUM);
Route::put('activarPreguntaEncuesta', [EncuestasController::class, 'activarPreguntaEncuesta'])->middleware($SANCTUM);
Route::post('enviarEncuesta', [EncuestasController::class, 'enviarEncuesta'])->middleware($SANCTUM);
Route::post('enviarEncuestaUsuario', [EncuestasController::class, 'enviarEncuestaUsuario'])->middleware($SANCTUM);

//ENCUESTAS PARA EL USUARIO
Route::get('getEncuestaPorTipo', [EncuestasController::class, 'getEncuestaPorTipo']);
Route::get('getRespuestasEncuestaPorCanje', [EncuestasController::class, 'getRespuestasEncuestaPorCanje']);
Route::post('addRespuestasEncuestaUsuario', [EncuestasController::class, 'addRespuestasEncuestaUsuario']);

//RESPUESTAS DE LA ENCUESTA
Route::get('getRespuestasPorEncuesta', [EncuestasController::class, 'getRespuestasPorEncuesta'])->middleware($SANCTUM);
Route::get('getRespuestasPorCanje', [EncuestasController::class, 'getRespuestasPorCanje'])->middleware($SANCTUM);

//NOTIFICACIONES
Route::put('removeNotificacion', [NotificacionesController::class, 'removeNotificacion'])->middleware($SANCTUM);
Route::get('getNotificacionesPorUsuario', [NotificacionesController::class, 'getNotificacionesPorUsuario'])->middleware($SANCTUM);

//CONFIGURACION
Route::post('crearVariablesGlobales', [ConfiguracionesController::class, 'crearVariablesGlobales'])->middleware($SANCTUM);
Route::post('crearPlataforma', [ConfiguracionesController::class, 'crearPlataforma'])->middleware($SANCTUM);
Route::get('getVariablesGlobales', [ConfiguracionesController::class, 'getVariablesGlobales'])->middleware($SANCTUM);
Route::get('getPlataformas', [ConfiguracionesController::class, 'getPlataformas'])->middleware($SANCTUM);
Route::get('getProductosSincronizados', [ConfiguracionesController::class, 'getProductosSincronizados'])->middleware(middleware: $SANCTUM);
Route::put('sincronizarVariablesEnProductos', [ConfiguracionesController::class, 'sincronizarVariablesEnProductos'])->middleware(middleware: $SANCTUM);
