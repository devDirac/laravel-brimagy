<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PreviewEmail extends Command
{
    protected $signature = 'email:preview';
    protected $description = 'Preview email template';

    public function handle()
    {
        $urlval = env('APP_FRONT_URL') . "/validar-canje/123456";
        $codigo = '123456';
        $canje = (object) [
            'folio' => 'CANJE-001',
            'nombre_usuario' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'phone' => '5551234567',
            'nombre_premio' => 'Premio Ejemplo',
            'puntos_canjeados' => 1000,
            'calle' => 'Av. Principal',
            'numero_calle' => '123',
            'colonia' => 'Centro',
            'municipio' => 'Ciudad de México',
            'codigo_postal' => '06000'
        ];

        $html = view('emails.validacion-canje', compact('canje', 'codigo', 'urlval'))->render();

        File::put(public_path('preview-email.html'), $html);

        $this->info('Email preview saved to: ' . public_path('preview-email.html'));
        $this->info('Open: http://localhost:3000/preview-email.html');
    }
}
