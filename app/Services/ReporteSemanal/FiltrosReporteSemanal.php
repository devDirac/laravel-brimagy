<?php

namespace App\Services\ReporteSemanal;

use Illuminate\Http\Request;

final class FiltrosReporteSemanal
{
    public function __construct(
        public readonly string $plataforma = 'puntotes',
        public readonly ?int $idPeriodo = null,
        public readonly ?int $semana = null,
    ) {}

    public static function desdeRequest(Request $request): self
    {
        return new self(
            plataforma: $request->input('plataforma') ?: 'puntotes',
            idPeriodo: $request->filled('id_periodo') ? (int) $request->input('id_periodo') : null,
            semana: $request->filled('semana') ? (int) $request->input('semana') : null,
        );
    }

    public function nombrePlataforma(): string
    {
        return str_replace('_', ' ', mb_strtolower(trim($this->plataforma)));
    }
}
