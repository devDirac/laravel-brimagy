<?php

namespace App\Utils;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Mail\MiCorreo;
use Mail;

class MailSend extends BaseController
{

    public function sendMailPro($data, $blade = 'confirmation', $subject = 'Confirm account')
    {
        try {
            Mail::to($data['correo'])->send(new MiCorreo(
                $subject,
                $data,
                $blade
            ));
            return $this->sendResponse('exito al enviar correo');
        } catch (\Throwable $th) {
            return $this->sendError('Error', $th, 500);
        }
    }
}
