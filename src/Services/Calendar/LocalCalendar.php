<?php
/**
 * Local Calendar Strategy
 * Implements CalendarInterface using only the local MySQL database.
 * Ideal for clients without Microsoft 365 / Outlook integration.
 * 
 * @package WhiteLabel\Services\Calendar
 */

declare(strict_types=1);

namespace Services\Calendar;

require_once __DIR__ . '/../../Contracts/CalendarInterface.php';
require_once __DIR__ . '/../../Database.php';

use Contracts\CalendarInterface;
use Database;
use PDO;
use PDOException;

class LocalCalendar implements CalendarInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connect();
    }

    /**
     * Check availability by querying appointments table
     * Uses overlap detection: existing.start < new.end AND existing.end > new.start
     */
    public function checkAvailability(string $startTime, string $endTime, ?int $branchId = null): bool
    {
        try {
            $sql = "SELECT COUNT(*) as conflicts 
                    FROM appointments 
                    WHERE start_time < :endTime 
                    AND end_time > :startTime 
                    AND status != 'cancelled'";
            
            $params = [
                ':startTime' => $startTime,
                ':endTime' => $endTime
            ];

            // Add branch filter if provided
            if ($branchId !== null) {
                $sql .= " AND branch_id = :branchId";
                $params[':branchId'] = $branchId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int) ($result['conflicts'] ?? 0) === 0;

        } catch (PDOException $e) {
            error_log("LocalCalendar::checkAvailability Error: " . $e->getMessage());
            // On error, assume unavailable for safety
            return false;
        }
    }

    /**
     * Create event - generates a local ID
     * The actual appointment is saved by the calling code in reservar.php
     * This just returns an identifier for the calendar_event_id field
     */
    public function createEvent(
        string $subject,
        string $startTime,
        string $endTime,
        string $description,
        string $location = ''
    ): ?string {
        // Generate a local event ID
        // Format: LOCAL_[timestamp]_[random]
        return 'LOCAL_' . time() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Delete event - for local strategy this is a no-op
     * The appointment deletion is handled directly in the database
     */
    public function deleteEvent(string $eventId): bool
    {
        // Local events don't require external API calls
        // The appointment row will be deleted/cancelled directly
        if (str_starts_with($eventId, 'LOCAL_')) {
            return true;
        }
        
        // If it's an old Outlook mock ID, also return true
        if (str_starts_with($eventId, 'MOCK_')) {
            return true;
        }

        return true;
    }
}
