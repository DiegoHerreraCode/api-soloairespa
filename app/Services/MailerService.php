<?php

namespace App\Services;

use App\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * Service MailerService
 * 
 * Servicio centralizado para el envío de correos electrónicos dinámicos y notificaciones.
 */
class MailerService
{
    public function __construct()
    {
    }

    /**
     * Envía un correo electrónico dinámico con soporte de destinatarios principales, CC, BCC y archivos adjuntos.
     *
     * @param array $config Configuración con arreglos de destinatarios ['to' => [], 'cc' => [], 'bcc' => []]
     * @param string $asunto Asunto del mensaje
     * @param string $vista Vista Blade a renderizar
     * @param array $data Parámetros y datos a inyectar en la vista
     * @param array $adjuntos Rutas locales en el servidor de archivos a adjuntar
     * @return mixed
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

        return $mail->send(new Mailer($asunto, $vista, $data, $adjuntos));
    }
}
