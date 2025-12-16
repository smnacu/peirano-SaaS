<?php
/**
 * Outlook Calendar Strategy
 * Implements CalendarInterface using Microsoft Graph API.
 * For clients with Microsoft 365 / Outlook integration.
 * 
 * @package WhiteLabel\Services\Calendar
 */

declare(strict_types=1);

namespace Services\Calendar;

require_once __DIR__ . '/../../Contracts/CalendarInterface.php';

use Contracts\CalendarInterface;

class OutlookCalendar implements CalendarInterface
{
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $userId;
    private string $timezone;

    public function __construct()
    {
        // Try to load from IntegrationService
        $config = [];
        if (class_exists('IntegrationService')) {
             $service = new \IntegrationService();
             $config = $service->getIntegrationConfig();
        } elseif (file_exists(__DIR__ . '/../../Services/IntegrationService.php')) {
             require_once __DIR__ . '/../../Services/IntegrationService.php';
             $service = new \IntegrationService();
             $config = $service->getIntegrationConfig();
        }

        $this->tenantId = $config['ms_tenant_id'] ?? (defined('MS_TENANT_ID') ? MS_TENANT_ID : 'common');
        $this->clientId = $config['ms_client_id'] ?? (defined('MS_CLIENT_ID') ? MS_CLIENT_ID : '');
        $this->clientSecret = $config['ms_client_secret'] ?? (defined('MS_CLIENT_SECRET') ? MS_CLIENT_SECRET : '');
        // For now, userId is not in the form, assumes same as auth or configured elsewhere. 
        // If "internal credentials" means the system acts as one specific user, we need that ID too.
        // I'll leave defined() fallback for user_id or add it to setup later if needed.
        $this->userId =  defined('MS_CALENDAR_USER') ? MS_CALENDAR_USER : ''; 
        $this->timezone = 'America/Argentina/Buenos_Aires';
    }

    /**
     * Get OAuth2 access token from Microsoft
     */
    private function getAccessToken(): ?string
    {
        // If credentials not configured, return null (mock mode)
        if (empty($this->clientId) || empty($this->clientSecret) || $this->clientId === 'placeholder') {
            return null;
        }

        $url = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        
        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("OutlookCalendar Token Error (cURL): {$error}");
            return null;
        }

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['access_token'] ?? null;
        }

        error_log("OutlookCalendar Token Error (HTTP {$httpCode}): {$response}");
        return null;
    }

    /**
     * Format datetime for Microsoft Graph API
     */
    private function formatDateTime(string $datetime): string
    {
        return str_replace(' ', 'T', $datetime);
    }

    /**
     * Check availability using Microsoft Graph calendarView
     */
    public function checkAvailability(string $startTime, string $endTime, ?int $branchId = null): bool
    {
        $token = $this->getAccessToken();

        // If no token (mock mode), assume available
        if ($token === null) {
            return true;
        }

        $start = $this->formatDateTime($startTime);
        $end = $this->formatDateTime($endTime);

        $url = "https://graph.microsoft.com/v1.0/users/{$this->userId}/calendarView"
             . "?startDateTime={$start}&endDateTime={$end}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                "Prefer: outlook.timezone=\"{$this->timezone}\""
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            // If there are events in this time range, it's NOT available
            return empty($data['value']);
        }

        error_log("OutlookCalendar Availability Error (HTTP {$httpCode}): {$response}");
        // On error, assume unavailable for safety
        return false;
    }

    /**
     * Create event using Microsoft Graph API
     */
    public function createEvent(
        string $subject,
        string $startTime,
        string $endTime,
        string $description,
        string $location = ''
    ): ?string {
        $token = $this->getAccessToken();

        // If no token (mock mode), return fake ID
        if ($token === null) {
            return 'MOCK_OUTLOOK_' . time() . '_' . bin2hex(random_bytes(4));
        }

        $url = "https://graph.microsoft.com/v1.0/users/{$this->userId}/events";

        // Build event data
        $eventData = [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => nl2br(htmlspecialchars($description))
            ],
            'start' => [
                'dateTime' => $this->formatDateTime($startTime),
                'timeZone' => $this->timezone
            ],
            'end' => [
                'dateTime' => $this->formatDateTime($endTime),
                'timeZone' => $this->timezone
            ],
            'location' => [
                'displayName' => $location ?: 'Planta'
            ],
            'transactionId' => uniqid('evt_', true)
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($eventData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201) {
            $data = json_decode($response, true);
            return $data['id'] ?? null;
        }

        error_log("OutlookCalendar CreateEvent Error (HTTP {$httpCode}): {$response}");
        return null;
    }

    /**
     * Delete event using Microsoft Graph API
     */
    public function deleteEvent(string $eventId): bool
    {
        // Handle mock/local IDs
        if (str_starts_with($eventId, 'MOCK_') || str_starts_with($eventId, 'LOCAL_')) {
            return true;
        }

        $token = $this->getAccessToken();
        if ($token === null) {
            return false;
        }

        $url = "https://graph.microsoft.com/v1.0/users/{$this->userId}/events/{$eventId}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}"
            ]
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 204 = No Content = Successfully deleted
        return $httpCode === 204;
    }
}
