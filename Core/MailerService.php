<?php
namespace App\Core;

use Dotenv\Dotenv;
use Exception;
use League\OAuth2\Client\Provider\Google;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\OAuth;
use PHPMailer\PHPMailer\PHPMailer;

final class MailerService
{
    private bool $cargado = false;

    public function __construct(string $envDir = null, string $envFile = 'email.env')
    {
        $dir = $envDir ?? ($_SERVER['DOCUMENT_ROOT'] . '/envs');
        if (is_dir($dir) && file_exists($dir . '/' . $envFile)) {
            $dotenv = Dotenv::createImmutable($dir, [$envFile]);
            $dotenv->load();
            $this->cargado = true;
        }
    }

    public function enviar(string $para, string $asunto, string $html): array
    {
        try {
            if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
                return ['respuesta' => 'error', 'mensaje' => 'Correo de destino no válido'];
            }
            if (!$this->cargado) {
                return ['respuesta' => 'error', 'mensaje' => 'Variables de email no cargadas'];
            }

            $aviso = '
            <div style="margin-top:32px; padding:12px 16px; border-top:1px solid #e0e0e0; background-color:#f9f9f9; font-family:Arial,sans-serif; font-size:12px; color:#888888; line-height:1.5;">
                <strong>Mensaje automático:</strong> No responda a este correo. 
                Este mensaje ha sido enviado a través de un sistema automatizado que no permite 
                recibir ni procesar respuestas enviadas a esta dirección.
            </div>';

            $provider = new Google([
                'clientId'     => $_ENV['CLIENTID'],
                'clientSecret' => $_ENV['CLIENTSECRET'],
            ]);

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->AuthType   = 'XOAUTH2';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setOAuth(new OAuth([
                'provider'      => $provider,
                'clientId'      => $_ENV['CLIENTID'],
                'clientSecret'  => $_ENV['CLIENTSECRET'],
                'refreshToken'  => $_ENV['REFRESHTOKEN'],
                'userName'      => $_ENV['EMAIL'],
            ]));

            $mail->setFrom($_ENV['EMAIL'], $_ENV['EMAIL_FROM_NAME'] ?? 'Notificaciones');
            $mail->addAddress($para);
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $html . $aviso;

            $mail->send();
            return ['respuesta' => 'success', 'mensaje' => 'Correo enviado correctamente'];

        } catch (PHPMailerException $e) {
            return ['respuesta' => 'error', 'mensaje' => 'Error de PHPMailer: ' . $e->getMessage()];
        } catch (Exception $e) {
            return ['respuesta' => 'error', 'mensaje' => 'Error general: ' . $e->getMessage()];
        }
    }
}