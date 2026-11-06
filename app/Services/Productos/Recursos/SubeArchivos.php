<?php

namespace App\Services\Productos\Recursos;

use App\Models\CatalogoProductos;
use App\Services\Plataformas\PlataformaCatalogo;

/** Recursos que se crean subiendo archivos (fotos del producto y fotos promocionales). */
interface SubeArchivos
{
    /** Campo del request con los archivos (arreglo). */
    public function campoArchivos(): string;

    /** Carpeta en el disco privado. */
    public function carpetaPrivada(): string;

    /** Columna remota donde va el nombre del archivo (photo, offer…). */
    public function columnaArchivoRemota(): string;

    /** Carpeta del FTP donde se sube, o null si no se puede determinar. */
    public function carpetaFtp(PlataformaCatalogo $plataforma, CatalogoProductos $producto): ?string;
}
