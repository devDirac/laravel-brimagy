<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoriasController;
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
Route::put('editarProducto', [ProductosController::class, 'editarProducto'])->middleware($SANCTUM);
Route::delete('eliminarProducto', [ProductosController::class, 'eliminarProducto'])->middleware($SANCTUM);
Route::post('verificarSkus', [ProductosController::class, 'verificarSkus'])->middleware($SANCTUM);
Route::post('verificarSkuDisponible', [ProductosController::class, 'verificarSkuDisponible'])->middleware($SANCTUM);

Route::get('busquedaInteligenteBrimagy', [ProductosController::class, 'busquedaInteligenteBrimagy'])->middleware($SANCTUM);
