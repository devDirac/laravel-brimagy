<?php

use App\Http\Controllers\API\AlmacenController;
use App\Http\Controllers\API\EstadisticasController;
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
Route::get('getCatalogoTipoUsuarios', [UserController::class, 'getCatalogoTipoUsuarios'])->middleware($SANCTUM);

Route::post('recuperaContrasena', [AuthController::class, 'passwordRecoverSendLink']);
Route::post('recuperaContrasenaTokenValidacion', [AuthController::class, 'passwordRecoverTokenValidation']);
Route::post('actualizacionContrasena', [AuthController::class, 'passwordReset']);

//PROVEEDORES
Route::post('crearProveedor', [ProveedorController::class, 'crearProveedor'])->middleware($SANCTUM);
Route::get('getProveedores', [ProveedorController::class, 'getProveedores'])->middleware($SANCTUM);
Route::put('editarProveedor', [ProveedorController::class, 'editarProveedor'])->middleware($SANCTUM);
Route::delete('eliminarProveedor', [ProveedorController::class, 'eliminarProveedor'])->middleware($SANCTUM);
Route::put('asignarProveedor', [ProveedorController::class, 'asignarProveedor'])->middleware($SANCTUM);

//CATEGORIAS
Route::get('getCategorias', [CategoriasController::class, 'getCategorias'])->middleware($SANCTUM);
Route::post('crearCategoria', [CategoriasController::class, 'crearCategoria'])->middleware($SANCTUM);
Route::get('getCategoriasPrincipal', [CategoriasController::class, 'getCategoriasPrincipal'])->middleware($SANCTUM);
Route::put('editarCategoria', [CategoriasController::class, 'editarCategoria'])->middleware($SANCTUM);
Route::put('eliminarCategoria', [CategoriasController::class, 'eliminarCategoria'])->middleware($SANCTUM);
Route::put('reactivarCategoria', [CategoriasController::class, 'reactivarCategoria'])->middleware($SANCTUM);
//CATALOGO PRODUCTOS
Route::post('crearProducto', [ProductosController::class, 'crearProducto'])->middleware($SANCTUM);
Route::get('getCatalogoProductos', [ProductosController::class, 'getCatalogoProductos'])->middleware($SANCTUM);
Route::get('getCatalogoProductosFisicos', [ProductosController::class, 'getCatalogoProductosFisicos'])->middleware($SANCTUM);
Route::get('getCatalogoProductosDigitales', [ProductosController::class, 'getCatalogoProductosDigitales'])->middleware($SANCTUM);
Route::post('editarProducto', [ProductosController::class, 'editarProducto'])->middleware($SANCTUM);
Route::delete('eliminarProducto', [ProductosController::class, 'eliminarProducto'])->middleware($SANCTUM);
Route::post('verificarSkus', [ProductosController::class, 'verificarSkus'])->middleware($SANCTUM);
Route::post('verificarSkuDisponible', [ProductosController::class, 'verificarSkuDisponible'])->middleware($SANCTUM);
Route::get('busquedaInteligenteBrimagy', [ProductosController::class, 'busquedaInteligenteBrimagy'])->middleware($SANCTUM);
Route::get('getBitacoraProductoPorId', [ProductosController::class, 'getBitacoraProductoPorId'])->middleware($SANCTUM);
Route::post('verificarIdProductoBrimagy', [ProductosController::class, 'verificarIdProductoBrimagy'])->middleware($SANCTUM);
Route::put('marcarNoDisponible', [ProductosController::class, 'marcarNoDisponible'])->middleware($SANCTUM);
Route::put('marcarDisponible', [ProductosController::class, 'marcarDisponible'])->middleware($SANCTUM);
//COLORES PRODUCTO
Route::post('crearEditarColorProducto', [ProductosController::class, 'crearEditarColorProducto'])->middleware($SANCTUM);
Route::get('getProductoColorPorId', [ProductosController::class, 'getProductoColorPorId'])->middleware($SANCTUM);
Route::put('activarColorProducto', [ProductosController::class, 'activarColorProducto'])->middleware($SANCTUM);
Route::put('desactivarColorProducto', [ProductosController::class, 'desactivarColorProducto'])->middleware($SANCTUM);
//TALLAS PRODUCTO
Route::post('crearEditarTallaProducto', [ProductosController::class, 'crearEditarTallaProducto'])->middleware($SANCTUM);
Route::get('getProductoTallaPorId', [ProductosController::class, 'getProductoTallaPorId'])->middleware($SANCTUM);
Route::put('activarTallaProducto', [ProductosController::class, 'activarTallaProducto'])->middleware($SANCTUM);
Route::put('desactivarTallaProducto', [ProductosController::class, 'desactivarTallaProducto'])->middleware($SANCTUM);
//FOTOS DEL PRODUCTO
Route::post('subirFotosProducto', [ProductosController::class, 'subirFotosProducto'])->middleware($SANCTUM);
Route::get('getProductoFotoPorId', [ProductosController::class, 'getProductoFotoPorId'])->middleware($SANCTUM);
Route::put('activarFotosProducto', [ProductosController::class, 'activarFotosProducto'])->middleware($SANCTUM);
Route::put('desactivarFotosProducto', [ProductosController::class, 'desactivarFotosProducto'])->middleware($SANCTUM);
//FOTOS PROMO DEL PRODUCTO
Route::post('subirFotosPromoProducto', [ProductosController::class, 'subirFotosPromoProducto'])->middleware($SANCTUM);
Route::get('getProductoFotoPromoPorId', [ProductosController::class, 'getProductoFotoPromoPorId'])->middleware($SANCTUM);
Route::put('activarFotosPromoProducto', [ProductosController::class, 'activarFotosPromoProducto'])->middleware($SANCTUM);
Route::put('desactivarFotosPromoProducto', [ProductosController::class, 'desactivarFotosPromoProducto'])->middleware($SANCTUM);
//MONTOS PRODUCTO DIGITAL
Route::post('crearEditarMontoProducto', [ProductosController::class, 'crearEditarMontoProducto'])->middleware($SANCTUM);
Route::get('getProductoMontoPorId', [ProductosController::class, 'getProductoMontoPorId'])->middleware($SANCTUM);
Route::put('activarMontoProducto', [ProductosController::class, 'activarMontoProducto'])->middleware($SANCTUM);
Route::put('desactivarMontoProducto', [ProductosController::class, 'desactivarMontoProducto'])->middleware($SANCTUM);
//FOTOS MONTO
Route::post('subirFotoMonto', [ProductosController::class, 'subirFotoMonto'])->middleware($SANCTUM);
Route::get('getFotoMontoPorId', [ProductosController::class, 'getFotoMontoPorId'])->middleware($SANCTUM);
Route::put('activarFotoMonto', [ProductosController::class, 'activarFotoMonto'])->middleware($SANCTUM);
Route::put('desactivarFotoMonto', [ProductosController::class, 'desactivarFotoMonto'])->middleware($SANCTUM);
//CATALOGO CANJES
Route::get('getCanjes', [CanjesController::class, 'getCanjes'])->middleware($SANCTUM);

//VALIDACIÓN DE IDENTIDAD
Route::post('enviarValidacion', [CanjesController::class, 'enviarValidacion'])->middleware($SANCTUM);
Route::post('solicitarCodigoValidacion', [CanjesController::class, 'solicitarCodigoValidacion']);
Route::get('getCodigoVerificacionById', [CanjesController::class, 'getCodigoVerificacionById']);
Route::get('getCanjeById', [CanjesController::class, 'getCanjeById']);
Route::post('validarIdentidadPorCodigo', [CanjesController::class, 'validarIdentidadPorCodigo']);
Route::post('enviarValidacionSinProveedor', [CanjesController::class, 'enviarValidacionSinProveedor'])->middleware($SANCTUM);
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
Route::post('registrarNuevoPrecio', [ProductosController::class, 'registrarNuevoPrecio'])->middleware($SANCTUM);
Route::put('enviarANuevoProveedor', [OrdenCompraController::class, 'enviarANuevoProveedor'])->middleware($SANCTUM);
//ALMACEN DE PRODUCTOS
Route::get('getProductosAlmacen', [AlmacenController::class, 'getProductosAlmacen'])->middleware($SANCTUM);
Route::get('getProductoAlmacenPorId', [AlmacenController::class, 'getProductoAlmacenPorId'])->middleware($SANCTUM);
Route::put('recibirProductoAlmacen', [AlmacenController::class, 'recibirProductoAlmacen'])->middleware($SANCTUM);
Route::put('addGuiaProductoAlmacen', [AlmacenController::class, 'addGuiaProductoAlmacen'])->middleware($SANCTUM);
Route::put('enviarProductoAlmacen', [AlmacenController::class, 'enviarProductoAlmacen'])->middleware($SANCTUM);
Route::put('confirmarRecepcionProductoAlmacen', [AlmacenController::class, 'confirmarRecepcionProductoAlmacen'])->middleware($SANCTUM);
Route::post('registrarNuevoPrecioAlmacen', [AlmacenController::class, 'registrarNuevoPrecioAlmacen'])->middleware($SANCTUM);
Route::put('registrarMeiNoSerie', [AlmacenController::class, 'registrarMeiNoSerie'])->middleware($SANCTUM);

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
Route::get('getProductosSincronizados', [ConfiguracionesController::class, 'getProductosSincronizados'])->middleware($SANCTUM);
Route::get('getVariablesGlobalesPorPlataforma', [ConfiguracionesController::class, 'getVariablesGlobalesPorPlataforma'])->middleware($SANCTUM);
Route::put('sincronizarVariablesEnProductos', [ConfiguracionesController::class, 'sincronizarVariablesEnProductos'])->middleware($SANCTUM);

//QUERYS DE LA BASE DE DATOS DE BRIMAGY
Route::get('getCatalogoProductosDigitalesBrimagy', [ProductosController::class, 'getCatalogoProductosDigitalesBrimagy'])->middleware($SANCTUM);
Route::get('getCatalogoClubBohnBrimagy', [ProductosController::class, 'getCatalogoClubBohnBrimagy'])->middleware($SANCTUM);

//ESTADISTICAS
Route::get('getEstadisticasHome', [EstadisticasController::class, 'getEstadisticasHome'])->middleware($SANCTUM);
Route::get('getEstadisticasCanjeados', [EstadisticasController::class, 'getEstadisticasCanjeados'])->middleware($SANCTUM);
Route::get('getEstadisticasPuntosCategoria', [EstadisticasController::class, 'getEstadisticasPuntosCategoria'])->middleware($SANCTUM);
Route::get('getEstadisticasPuntosPorTipoProducto', [EstadisticasController::class, 'getEstadisticasPuntosPorTipoProducto'])->middleware($SANCTUM);
Route::get('getEstadisticasComparativa', [EstadisticasController::class, 'getEstadisticasComparativa'])->middleware($SANCTUM);

//USUARIOS DE PLATAFORMA
Route::post('crearUsuarioPlataforma', [UserController::class, 'crearUsuarioPlataforma'])->middleware($SANCTUM);
Route::get('getCheckEmailUsuarioPlataforma', [UserController::class, 'getCheckEmailUsuarioPlataforma'])->middleware($SANCTUM);
Route::get('getUsuariosPlataforma', [UserController::class, 'getUsuariosPlataforma'])->middleware($SANCTUM);
Route::get('getTipoUsuarios', [UserController::class, 'getTipoUsuarios'])->middleware($SANCTUM);
