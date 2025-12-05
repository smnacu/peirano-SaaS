<?php
/**
 * Calendar Interface - Strategy Pattern
 * Defines the contract for calendar implementations.
 * 
 * @package WhiteLabel\Contracts
 */

declare(strict_types=1);

namespace Contracts;

interface CalendarInterface
{
    /**
     * Check if a time slot is available
     * 
     * @param string $startTime Start datetime (Y-m-d H:i:s)
     * @param string $endTime End datetime (Y-m-d H:i:s)
     * @param int|null $branchId Optional branch filter
     * @return bool True if available, false otherwise
     */
    public function checkAvailability(string $startTime, string $endTime, ?int $branchId = null): bool;

    /**
     * Create a calendar event
     * 
     * @param string $subject Event title/subject
     * @param string $startTime Start datetime (Y-m-d H:i:s)
     * @param string $endTime End datetime (Y-m-d H:i:s)
     * @param string $description Event description/body
     * @param string $location Event location
     * @return string|null Event ID if successful, null otherwise
     */
    public function createEvent(
        string $subject,
        string $startTime,
        string $endTime,
        string $description,
        string $location = ''
    ): ?string;

    /**
     * Delete a calendar event
     * 
     * @param string $eventId Event identifier
     * @return bool True if deleted successfully
     */
    public function deleteEvent(string $eventId): bool;
}
