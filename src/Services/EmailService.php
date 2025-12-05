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
    public function send(string $to, string $subject, string $body): bool
    {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        
        // Dynamic sender from branding
        $fromName = brand('name');
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