<?php

namespace App\Services\Productos\Recursos;

use App\Models\CatalogoProductos;
use App\Models\FotosOfertas;
use App\Services\Plataformas\PlataformaCatalogo;

/** Fotos promocionales (ofertas): todas van a la carpeta Ofertas del FTP. */
class FotoPromoProducto extends RecursoProducto implements SubeArchivos
{
    const CARPETA_FTP = 'Ofertas';

    public function claveRemota(): ?string { return PlataformaCatalogo::FOTOS_PROMO; }
    public function modeloLocal(): string { return FotosOfertas::class; }
    public function campoId(): string { return 'id_foto_promo'; }
    public function campoIdRemoto(): ?string { return 'id_foto_promo_brimagy'; }
    public function campoDescriptivo(): string { return 'nombre'; }
    public function tablaBitacora(): string { return 'dc_fotos_promo_producto'; }
    public function nombre(): string { return 'foto'; }
    public function esFemenino(): bool { return true; }

    public function campoArchivos(): string { return 'fotos_promo_producto'; }
    public function carpetaPrivada(): string { return 'fotos_promo_producto'; }
    public function columnaArchivoRemota(): string { return 'offer'; }

    public function carpetaFtp(PlataformaCatalogo $plataforma, CatalogoProductos $producto): ?string
    {
        return self::CARPETA_FTP;
    }
}
