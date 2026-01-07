<?php

namespace App\Services;

use App\Services\WhatsApp\UltramsgController;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $controller;

    public function __construct()
    {
        $token = env('WHATSAPP_TOKEN', 'gmxwvtq6ts9up00d');
        $instanceId = env('WHATSAPP_INSTANCE_ID', 'instance80546');

        $this->controller = new UltramsgController($token, $instanceId);
    }

    /**
     * Enviar mensaje de texto
     */
    public function sendMessage($telefono, $mensaje)
    {
        try {
            $response = $this->controller->sendChatMessage($telefono, $mensaje);
            Log::info("WhatsApp enviado a {$telefono}", ['response' => $response]);
            return $response;
        } catch (\Exception $e) {
            Log::error("Error al enviar WhatsApp: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Enviar documento
     */
    public function sendDocument($telefono, $nombreDocumento, $rutaDocumento, $subtitulo = "")
    {
        try {
            $response = $this->controller->sendDocumentMessage(
                $telefono,
                $nombreDocumento,
                $rutaDocumento,
                $subtitulo
            );
            Log::info("Documento WhatsApp enviado a {$telefono}");
            return $response;
        } catch (\Exception $e) {
            Log::error("Error al enviar documento WhatsApp: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Enviar link
     */
    public function sendLink($telefono, $link)
    {
        try {
            $response = $this->controller->sendLinkMessage($telefono, $link);
            Log::info("Link WhatsApp enviado a {$telefono}");
            return $response;
        } catch (\Exception $e) {
            Log::error("Error al enviar link WhatsApp: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Enviar imagen
     */
    public function sendImage($telefono, $imagen, $caption = "")
    {
        try {
            $response = $this->controller->sendImageMessage($telefono, $imagen, $caption);
            Log::info("Imagen WhatsApp enviada a {$telefono}");
            return $response;
        } catch (\Exception $e) {
            Log::error("Error al enviar imagen WhatsApp: " . $e->getMessage());
            return null;
        }
    }
}
