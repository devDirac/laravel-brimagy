<?php

namespace App\Services;

use App\Contracts\NotificadorCotizacion;
use App\Mail\EnvioCotizacion;
use App\Models\CatalogoProveedores;
use App\Models\OrdenCompra;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificadorCotizacionWhatsAppCorreo implements NotificadorCotizacion
{
    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    public function cotizacionEnviada(OrdenCompra $orden, array $productos): void
    {
        $proveedor = CatalogoProveedores::find($orden->id_proveedor);
        if (!$proveedor) {
            return;
        }

        $url = config('app.url_frontend') . '/validar-ordencompra/' . $this->token($orden->no_orden);
        $total = collect($productos)->sum('importe_total');

        try {
            if ($proveedor->telefono) {
                $msg = "Se le ha enviado una cotización de productos para su validación:\n\n📦 *Productos solicitados:*\n";
                foreach ($productos as $p) {
                    $msg .= "• {$p['nombre_producto']}\n  Cantidad: {$p['cantidad_producto']}\n"
                        . '  Precio: $' . number_format($p['precio_unitario'], 2) . "\n"
                        . '  Total: $' . number_format($p['importe_total'], 2) . "\n\n";
                }
                $msg .= '💰 *Total General: $' . number_format($total, 2) . "*\n\n🔗 Link para revisar y confirmar:\n{$url}\n";
                $this->whatsapp->sendMessage($proveedor->telefono, $msg);
            }
        } catch (\Throwable $e) {
            Log::error("WhatsApp cotización {$orden->no_orden}: " . $e->getMessage());
        }

        try {
            if ($proveedor->correo) {
                $datos = (object) [
                    'productos' => $productos,
                    'total_general' => $total,
                    'total_iva' => collect($productos)->sum('iva'),
                    'url_validacion' => $url,
                ];
                Mail::to($proveedor->correo)->send(new EnvioCotizacion($datos, $url));
            }
        } catch (\Throwable $e) {
            Log::error("Correo cotización {$orden->no_orden}: " . $e->getMessage());
        }
    }

    // Mismo formato que encriptarCorto del controlador
    private function token(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor . '|' . time()), '+/', '-_'), '=');
    }
}