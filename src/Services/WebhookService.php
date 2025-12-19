<?php
/**
 * WebhookService
 * Dispatches events to configured external URLs.
 */
require_once __DIR__ . '/../Database.php';

class WebhookService {
    
    private $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
    }

    /**
     * Trigger a webhook event
     * @param string $event_name (e.g., 'appointment.blocked', 'appointment.created')
     * @param array $payload Data to send
     */
    public function trigger(string $event_name, array $payload) {
        // 1. Check if we have a URL configured for this event type (stored in system_settings)
        // For simple MVP, we look for a generic 'webhook_url' in settings.
        // Or separate columns 'webhook_url_alerts', etc.
        // Let's assume a generic settings key 'webhook_url' for all events for now.
        
        $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'webhook_url'");
        $stmt->execute();
        $url = $stmt->fetchColumn();

        if (!$url) return; // No webhook configured

        // 2. Prepare Payload
        $data = [
            'event' => $event_name,
            'timestamp' => date('c'),
            'data' => $payload
        ];

        // 3. Send Async (Fire and Forget) - PHP Implementation Limit: 
        // Real async is hard in vanilla PHP without queues. 
        // We will do a short timeout cURL.
        $this->sendRequest($url, $data);
    }

    private function sendRequest($url, $data) {
        $ch = curl_init($url);
        $json = json_encode($data);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Timeout fast to not block user
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1000);

        curl_exec($ch);
        curl_close($ch);
    }
}
