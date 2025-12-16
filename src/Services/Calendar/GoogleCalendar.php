<?php
/**
 * Google Calendar Strategy
 * Implements CalendarInterface using Google Calendar API.
 * 
 * @package WhiteLabel\Services\Calendar
 */

declare(strict_types=1);

namespace Services\Calendar;

require_once __DIR__ . '/../../Contracts/CalendarInterface.php';

use Contracts\CalendarInterface;

class GoogleCalendar implements CalendarInterface
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

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

        $this->clientId = $config['google_client_id'] ?? '';
        $this->clientSecret = $config['google_client_secret'] ?? '';
        $this->redirectUri = defined('BASE_URL') ? BASE_URL . 'callback_google.php' : '';
    }

    public function checkAvailability(string $startTime, string $endTime, ?int $branchId = null): bool
    {
        // TODO: Implement Google Calendar availability check
        // For now, mirroring implies we just push events, but if checking needed:
        return true; 
    }

    public function createEvent(
        string $subject,
        string $startTime,
        string $endTime,
        string $description,
        string $location = ''
    ): ?string {
        // TODO: Implement Google Calendar event creation
        // Return null to indicate "not implemented yet" or mock ID
        return 'G_MOCK_' . uniqid();
    }

    public function deleteEvent(string $eventId): bool
    {
        return true;
    }
}
?>
