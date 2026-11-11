<?php

namespace App\Services\Canjes;

use Illuminate\Http\Request;

/**
 * Filtros de la consulta de canjes. Desacopla los servicios del Request HTTP.
 */
final class FiltrosCanjes
{
    public function __construct(
        public readonly string $plataforma = 'puntotes',
        public readonly ?string $tipoProducto = null,
        public readonly ?string $search = null,
        public readonly ?string $fecha1 = null,
        public readonly ?string $fecha2 = null,
    ) {
    }

    public static function desdeRequest(Request $request): self
    {
        return new self(
            plataforma: $request->input('plataforma') ?: 'puntotes',
            tipoProducto: $request->input('tipo_producto') ?: null,
            search: $request->input('search') ?: null,
            fecha1: $request->input('fecha1') ?: null,
            fecha2: $request->input('fecha2') ?: null,
        );
    }

    public function esClubBohn(): bool
    {
        return $this->plataforma === 'club_bohn';
    }
}