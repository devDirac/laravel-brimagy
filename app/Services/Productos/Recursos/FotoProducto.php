<?php

namespace App\Services\Productos\Recursos;

use App\Models\CatalogoProductos;
use App\Models\FotosProducto;
use App\Services\Plataformas\PlataformaCatalogo;

/** Fotos adicionales del producto */
class FotoProducto extends RecursoProducto implements SubeArchivos
{
    public function claveRemota(): ?string { return PlataformaCatalogo::FOTOS; }
    public function modeloLocal(): string { return FotosProducto::class; }
    public function campoId(): string { return 'id_foto'; }
    public function campoIdRemoto(): ?string { return 'id_foto_brimagy'; }
    public function campoDescriptivo(): string { return 'nombre'; }
    public function tablaBitacora(): string { return 'dc_fotos_producto'; }
    public function nombre(): string { return 'foto'; }
    public function esFemenino(): bool { return true; }

    public function campoArchivos(): string { return 'fotos_producto'; }
    public function carpetaPrivada(): string { return 'fotos_producto'; }
    public function columnaArchivoRemota(): string { return 'photo'; }

    public function carpetaFtp(PlataformaCatalogo $plataforma, CatalogoProductos $producto): ?string
    {
        return $plataforma->carpetaFtp($producto->id_catalogo);
    }
}
