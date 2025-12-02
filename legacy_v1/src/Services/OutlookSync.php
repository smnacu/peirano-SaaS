<?php
// src/Services/OutlookSync.php
require_once __DIR__ . '/../../config/config.php';

class OutlookSync {
    private $tenantId;
    private $clientId;
    private $clientSecret;
    private $userId;

    public function __construct() {
        // Tomamos constantes definidas en config/config.php
        // Usamos defined() para evitar errores si no están configuradas
        $this->tenantId = defined('MS_TENANT_ID') ? MS_TENANT_ID : '';
        $this->clientId = defined('MS_CLIENT_ID') ? MS_CLIENT_ID : '';
        $this->clientSecret = defined('MS_CLIENT_SECRET') ? MS_CLIENT_SECRET : '';
        $this->userId = defined('MS_CALENDAR_USER') ? MS_CALENDAR_USER : '';
    }

    /**
     * Obtiene el Token de acceso de Microsoft (OAuth2)
     */
    private function getAccessToken() {
        // MODO DESARROLLO / SIN CREDENCIALES
        if (empty($this->clientId) || empty($this->clientSecret)) {
            return null; // Retorna null para indicar que no hay conexión real
        }

        $url = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['access_token'] ?? null;
        }

        error_log("Outlook Sync Error (Token): $response");
        return null;
    }

    /**
     * Crea un evento en el calendario
     */
    public function createEvent($subject, $startTime, $endTime, $description, $locationName = 'Planta Peirano') {
        $token = $this->getAccessToken();

        // Si no hay token (porque no configuramos claves), simulamos éxito
        if (!$token) {
            return "MOCK_OUTLOOK_ID_" . uniqid(); 
        }

        $url = "https://graph.microsoft.com/v1.0/users/{$this->userId}/events";
        
        // Formato requerido por Microsoft Graph
        $eventData = [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $description
            ],
            'start' => [
                'dateTime' => str_replace(' ', 'T', $startTime),
                'timeZone' => 'America/Argentina/Buenos_Aires'
            ],
            'end' => [
                'dateTime' => str_replace(' ', 'T', $endTime),
                'timeZone' => 'America/Argentina/Buenos_Aires'
            ],
            'location' => [
                'displayName' => $locationName
            ],
            'transactionId' => uniqid()
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($eventData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 201) {
            $data = json_decode($response, true);
            return $data['id']; // ID real de Outlook
        }

        error_log("Outlook Sync Error (Create): $response");
        return null;
    }

    /**
     * Elimina un evento (útil si se cancela el turno)
     */
    public function deleteEvent($outlookId) {
        // Si es un ID falso (Mock), no hacemos nada
        if (strpos($outlookId, 'MOCK_') === 0) return true;

        $token = $this->getAccessToken();
        if (!$token) return false;

        $url = "https://graph.microsoft.com/v1.0/users/{$this->userId}/events/$outlookId";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $httpCode === 204;
    }

    /**
     * Verifica disponibilidad en el calendario
     */
    public function checkAvailability($startTime, $endTime, $branchId = null) {
        $token = $this->getAccessToken();

        // Si no hay token, asumimos disponible (Modo Mock/Dev)
        if (!$token) {
            return true;
        }

        $start = str_replace(' ', 'T', $startTime);
        $end = str_replace(' ', 'T', $endTime);

        // Consultamos calendarView
        $url = "https://graph.microsoft.com/v1.0/users/{$this->userId}/calendarView?startDateTime={$start}&endDateTime={$end}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Prefer: outlook.timezone=\"America/Argentina/Buenos_Aires\""
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            // Si hay eventos en 'value', entonces NO está disponible
            if (isset($data['value']) && count($data['value']) > 0) {
                return false;
            }
            return true;
        }

        // Si hay error en la API, logueamos y asumimos ocupado por precaución
        error_log("Outlook Sync Error (Check): $response");
        return false;
    }
}
?>