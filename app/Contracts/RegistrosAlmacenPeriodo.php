<?php

namespace App\Contracts;

use App\Services\ReporteSemanal\PeriodoSemanal;
use Illuminate\Support\Collection;

interface RegistrosAlmacenPeriodo
{
    public function obtener(PeriodoSemanal $periodo): Collection;
}
