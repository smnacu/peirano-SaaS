<?php
// src/Services/EmailService.php
require_once __DIR__ . '/../../config/config.php';

class EmailService {
    
    /**
     * Envía un correo genérico con formato HTML
     */
    public function send($to, $subject, $body) {
        // Headers para que el mail se interprete como HTML y no texto plano
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        
        // Remitente configurado en config.php
        $headers .= "From: Peirano Logística <" . SMTP_USER . ">" . "\r\n";
        $headers .= "Reply-To: " . SMTP_USER . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Envolvemos el mensaje en un template lindo
        $finalBody = $this->wrapTemplate($body);

        // Usamos la función nativa mail()
        // NOTA: Para producción real con alta entregabilidad, acá se conectaría PHPMailer
        return mail($to, $subject, $finalBody, $headers);
    }

    /**
     * Envía notificaciones automáticas de cambio de estado
     */
    public function sendStatusUpdate($toEmail, $userName, $status, $date) {
        $subject = "";
        $msg = "";
        $dateFormatted = date('d/m/Y H:i', strtotime($date));

        switch ($status) {
            case 'reserved': // Aprobado / Confirmado
                $subject = "✅ Turno Confirmado - Peirano Logística";
                $msg = "Hola <strong>$userName</strong>,<br><br>
                        Te confirmamos que tu turno para el <strong>$dateFormatted</strong> ha sido CONFIRMADO.<br>
                        Te esperamos en planta. Recordá traer DNI y EPP.";
                break;

            case 'rejected': // Rechazado
                $subject = "❌ Turno Rechazado";
                $msg = "Hola <strong>$userName</strong>,<br><br>
                        Lamentamos informarte que tu solicitud para el <strong>$dateFormatted</strong> no pudo ser aprobada.<br>
                        Por favor, intentá en otro horario o contactate con administración.";
                break;
            
            case 'pending_approval': // Recibido (Fijo)
                $subject = "⏳ Solicitud Recibida";
                $msg = "Hola <strong>$userName</strong>,<br><br>
                        Recibimos tu solicitud de turno fijo. La administración la revisará y te avisará por este medio cuando se apruebe.";
                break;
        }

        if ($subject) {
            return $this->send($toEmail, $subject, $msg);
        }
        return false;
    }

    /**
     * Plantilla base para que el mail tenga "cara" corporativa
     */
    private function wrapTemplate($content) {
        $year = date('Y');
        return "
        <div style='background-color: #f4f4f4; padding: 20px; font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
                <div style='background-color: #E30613; padding: 15px; text-align: center;'>
                    <h2 style='color: #ffffff; margin: 0;'>Peirano Logística</h2>
                </div>
                <div style='padding: 30px; color: #333333; line-height: 1.6;'>
                    $content
                </div>
                <div style='background-color: #eeeeee; padding: 15px; text-align: center; font-size: 12px; color: #777777;'>
                    &copy; $year Peirano Logística - Sistema de Turnos<br>
                    Por favor no respondas a este correo automático.
                </div>
            </div>
        </div>";
    }
}
?>