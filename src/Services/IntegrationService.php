<?php
// src/Services/IntegrationService.php

require_once __DIR__ . '/../Database.php';

class IntegrationService {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
        $this->ensureTableExists();
    }

    private function ensureTableExists() {
        $sql = "CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }

    public function getSetting(string $key, $default = null) {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    }

    public function setSetting(string $key, ?string $value) {
        if ($value === null) {
            $stmt = $this->pdo->prepare("DELETE FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
    }

    public function getIntegrationConfig() {
        return [
            'provider' => $this->getSetting('calendar_provider', 'local'),
            'google_client_id' => $this->getSetting('google_client_id'),
            'google_client_secret' => $this->getSetting('google_client_secret'),
            'google_refresh_token' => $this->getSetting('google_refresh_token'),
            'ms_client_id' => $this->getSetting('ms_client_id'),
            'ms_client_secret' => $this->getSetting('ms_client_secret'),
            'ms_tenant_id' => $this->getSetting('ms_tenant_id'),
            'ms_refresh_token' => $this->getSetting('ms_refresh_token'),
        ];
    }
    
    public function saveIntegrationConfig(array $data) {
        $this->setSetting('calendar_provider', $data['provider'] ?? 'local');
        
        // Save fields based on provider to keep DB clean(er) or save all
        if (isset($data['google_client_id'])) $this->setSetting('google_client_id', $data['google_client_id']);
        if (isset($data['google_client_secret'])) $this->setSetting('google_client_secret', $data['google_client_secret']);
        
        if (isset($data['ms_client_id'])) $this->setSetting('ms_client_id', $data['ms_client_id']);
        if (isset($data['ms_client_secret'])) $this->setSetting('ms_client_secret', $data['ms_client_secret']);
        if (isset($data['ms_tenant_id'])) $this->setSetting('ms_tenant_id', $data['ms_tenant_id']);
    }
}
?>
