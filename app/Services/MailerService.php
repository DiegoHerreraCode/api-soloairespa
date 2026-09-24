<?php

namespace App\Services;

use App\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

class MailerService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Envía un correo dinámico.
     * 
     * @param array $config ['to' => [], 'cc' => [], 'bcc' => []]
     * @param string $asunto
     * @param string $vista
     * @param array $data
     * @param array $adjuntos Rutas locales de archivos
     */
    public static function enviarCorreo(array $config, string $asunto, string $vista, array $data = [], array $adjuntos = [])
    {
        $mail = Mail::to($config['to'] ?? []);

        if (!empty($config['cc'])) {
            $mail->cc($config['cc']);
        }

        if (!empty($config['bcc'])) {
            $mail->bcc($config['bcc']);
        }

        $response = $mail->send(new Mailer($asunto, $vista, $data, $adjuntos));

        return $response;
    }

}
