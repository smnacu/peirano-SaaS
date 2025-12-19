<?php
/**
 * Email Service - White-Label SaaS
 * Sends emails with dynamic branding from configuration.
 * 
 * @package WhiteLabel\Services
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/branding.php';

class EmailService
{
    /**
     * Send a generic HTML email
     */
    require_once __DIR__ . '/SimpleSMTP.php';
    require_once __DIR__ . '/IntegrationService.php';

    /**
     * Send a generic HTML email
     */
    public function send(string $to, string $subject, string $body): bool
    {
        $integrationService = new \IntegrationService();
        $config = $integrationService->getIntegrationConfig();

        $fromName = $config['smtp_from_name'] ?: brand('name');

        if ($config['smtp_enabled'] === '1') {
             $smtp = new \SimpleSMTP(
                 $config['smtp_host'],
                 (int)$config['smtp_port'],
                 $config['smtp_user'],
                 $config['smtp_pass']
             );
             return $smtp->send($config['smtp_user'], $fromName, $to, $subject, $this->wrapTemplate($body));
        }

        // Fallback or Basic Mail
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        
        // Dynamic sender from branding
        $fromEmail = defined('SMTP_USER') ? SMTP_USER : 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Wrap message in branded template
        $finalBody = $this->wrapTemplate($body);

        return mail($to, $subject, $finalBody, $headers);
    }

    /**
     * Send status update notifications
     */
    public function sendStatusUpdate(string $toEmail, string $userName, string $status, string $date): bool
    {
        $subject = "";
        $msg = "";
        $dateFormatted = date('d/m/Y H:i', strtotime($date));
        $companyName = brand('name');

        switch ($status) {
            case 'reserved':
                $subject = "✅ Turno Confirmado - {$companyName}";
                $msg = "Hola <strong>{$userName}</strong>,<br><br>
                        Te confirmamos que tu turno para el <strong>{$dateFormatted}</strong> ha sido CONFIRMADO.<br>
                        Te esperamos en planta. Recordá traer DNI y EPP.";
                break;

            case 'rejected':
                $subject = "❌ Turno Rechazado - {$companyName}";
                $msg = "Hola <strong>{$userName}</strong>,<br><br>
                        Lamentamos informarte que tu solicitud para el <strong>{$dateFormatted}</strong> no pudo ser aprobada.<br>
                        Por favor, intentá en otro horario o contactate con administración.";
                break;
            
            case 'pending_approval':
                $subject = "⏳ Solicitud Recibida - {$companyName}";
                $msg = "Hola <strong>{$userName}</strong>,<br><br>
                        Recibimos tu solicitud de turno fijo. La administración la revisará y te avisará por este medio cuando se apruebe.";
                break;

            case 'approved':
                $subject = "✅ Cuenta Aprobada - {$companyName}";
                $msg = "Hola <strong>{$userName}</strong>,<br><br>
                        Tu cuenta ha sido aprobada. Ya podés ingresar al sistema y solicitar turnos.<br>
                        <a href='" . BASE_URL . "'>Ir al Sistema</a>";
                break;

            case 'blocked':
                $subject = "⛔ Cuenta Suspendida - {$companyName}";
                $msg = "Hola <strong>{$userName}</strong>,<br><br>
                        Detectamos 3 inasistencias consecutivas a tus turnos reservados. <br>
                        Tu cuenta ha sido suspendida temporalmente.<br><br>
                        Por favor, comunicate con la administración para regularizar tu situación.";
                break;

            case 'reminder':
                $subject = "⏰ Recordatorio de Turno - {$companyName}";
                $msg = "Hola <strong>{$userName}</strong>,<br><br>
                        Te recordamos que tenés un turno reservado para mañana <strong>{$dateFormatted}</strong>.<br>
                        Por favor, recordá asistir puntual o cancelar si no podés venir.";
                break;

            case 'cancelled':
                $subject = "❌ Turno Cancelado - {$companyName}";
                $msg = "Hola <strong>{$userName}</strong>,<br><br>
                        Tu turno para el <strong>{$dateFormatted}</strong> ha sido cancelado exitosamente.<br>
                        Si fue un error, por favor reservá nuevamente en el sistema.";
                break;
        }

        if ($subject) {
            return $this->send($toEmail, $subject, $msg);
        }
        
        return false;
    }

    /**
     * Wrap content in branded email template
     */
    private function wrapTemplate(string $content): string
    {
        $year = date('Y');
        $companyName = brand('name');
        $primaryColor = brand('primary');
        
        return "
        <div style='background-color: #f4f4f4; padding: 20px; font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
                <div style='background-color: {$primaryColor}; padding: 15px; text-align: center;'>
                    <h2 style='color: #ffffff; margin: 0;'>{$companyName}</h2>
                </div>
                <div style='padding: 30px; color: #333333; line-height: 1.6;'>
                    {$content}
                </div>
                <div style='background-color: #eeeeee; padding: 15px; text-align: center; font-size: 12px; color: #777777;'>
                    &copy; {$year} {$companyName} - Sistema de Turnos<br>
                    Por favor no respondas a este correo automático.
                </div>
            </div>
        </div>";
    }
}