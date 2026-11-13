<?php

namespace App\Services\ReporteSemanal;

use Illuminate\Http\Request;

final class FiltrosReporteSemanal
{
    public function __construct(
        public readonly ?int $idPeriodo = null,
        public readonly ?int $semana = null,
    ) {}

    public static function desdeRequest(Request $request): self
    {
        return new self(
            idPeriodo: $request->filled('id_periodo') ? (int) $request->input('id_periodo') : null,
            semana: $request->filled('semana') ? (int) $request->input('semana') : null,
        );
    }
}
