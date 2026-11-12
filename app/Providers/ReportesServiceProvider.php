<?php

namespace App\Providers;

use App\Contracts\RegistrosAlmacenPeriodo;
use App\Repositories\RegistrosAlmacenPeriodoRepository;
use Illuminate\Support\ServiceProvider;

class ReportesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RegistrosAlmacenPeriodo::class, RegistrosAlmacenPeriodoRepository::class);
    }
}
