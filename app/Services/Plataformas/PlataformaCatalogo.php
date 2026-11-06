<?php

namespace App\Services\Plataformas;

use Illuminate\Database\Eloquent\Model;

/**
 * Todo lo que cambia entre plataformas al guardar un producto y sus recursos.
 * Agregar una plataforma = crear una clase que implemente esto y registrarla en el resolver,
 * sin tocar los servicios.
 */
interface PlataformaCatalogo
{
    /** Claves de los recursos que viven en la BD de la plataforma (ver modelosRemotos). */
    const COLORES = 'colores';
    const TALLAS = 'tallas';
    const MONTOS = 'montos';
    const FOTOS = 'fotos';
    const FOTOS_PROMO = 'fotos_promo';

    /**
     * Valores cuando la plataforma no tiene VariablesGlobales:
     * fee_brimagy, envio_base, costo_caja, envio_extra y valor_factor.
     */
    public function valoresPorDefecto(): array;

    /** Subcategoría por su nombre (para la carga desde Excel). */
    public function buscarSubcategoriaPorNombre(?string $descripcion): ?Model;

    /** Carpeta del FTP de la categoría a la que pertenece la subcategoría, o null si no existe. */
    public function carpetaFtp($idSubcategoria): ?string;

    /** Conexiones de BD que usa la plataforma (para abrir transacciones en todas). */
    public function conexiones(): array;

    // ---------- Producto ----------

    /** Producto en la BD de la plataforma (Brimagy / Club). */
    public function buscarProductoRemoto($idRemoto): ?Model;

    /**
     * Crea el producto en la plataforma si todavía no existe ahí y regresa su id.
     * $datos usa las columnas remotas: desc, features, required_score, sub_category_id, photo_name, sku, TyC, validity.
     */
    public function crearProductoRemotoSiNoExiste($idRemoto, array $datos): int;

    /**
     * Actualiza en la plataforma exactamente los campos de $datos.
     * Lanza RecursoNoEncontradoException si el producto no existe.
     */
    public function actualizarProductoRemoto($idRemoto, array $datos): void;

    // ---------- Recursos del producto (colores, tallas, montos, fotos) ----------

    /**
     * Modelos donde vive un recurso en la plataforma: el primero es el principal
     * y los demás son copias (espejos) que se mantienen con el mismo id.
     *
     * @param string $recurso Una de las constantes COLORES, TALLAS, MONTOS, FOTOS, FOTOS_PROMO
     * @return string[]
     */
    public function modelosRemotos(string $recurso): array;

    /** Registro del recurso en el modelo principal de la plataforma. */
    public function buscarRegistroRemoto(string $recurso, $id): ?Model;

    /** Crea el registro en el modelo principal y en sus espejos. Regresa el id del principal. */
    public function crearRegistroRemoto(string $recurso, array $datos): int;

    /**
     * Actualiza el registro en el modelo principal y en sus espejos.
     * Lanza RecursoNoEncontradoException si no existe en el principal.
     */
    public function actualizarRegistroRemoto(string $recurso, $id, array $datos): void;

    /** Crea el color en la plataforma y regresa el id del registro remoto. */
    public function agregarColor(int $idRemoto, string $color): int;

    /** Crea la talla en la plataforma y regresa el id del registro remoto. */
    public function agregarTalla(int $idRemoto, string $talla): int;
}
