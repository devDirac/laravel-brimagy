<?php

namespace App\Services\Productos\Recursos;

use App\Models\FotoMontos;

/** Foto (gif) de los montos de un producto digital. Solo existe en local. */
class FotoMontoProducto extends RecursoProducto
{
    public function claveRemota(): ?string { return null; }
    public function modeloLocal(): string { return FotoMontos::class; }
    public function campoId(): string { return 'id_foto_monto'; }
    public function campoDescriptivo(): string { return 'nombre'; }
    public function tablaBitacora(): string { return 'dc_foto_montos'; }
    public function nombre(): string { return 'foto'; }
    public function esFemenino(): bool { return true; }
    public function ambito(): string { return 'monto'; }
}
