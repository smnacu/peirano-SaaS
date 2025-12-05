<?php
/**
 * API Check Slots - White-Label SaaS
 * Returns available time slots for a given date and branch.
 * Uses CalendarFactory to check availability via the configured strategy.
 * 
 * @package WhiteLabel\API
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Services/Calendar/CalendarFactory.php';

use Services\Calendar\CalendarFactory;

// JSON Response
header('Content-Type: application/json');

// Authentication check
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get parameters
$date = $_GET['date'] ?? '';
$branchId = (int) ($_GET['branch_id'] ?? 0);

if (empty($date) || $branchId === 0) {
    echo json_encode([]);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

// =====================================================
// SLOT CONFIGURATION
// These could come from branch settings in the future
// =====================================================

$startHour = 8;   // 08:00
$endHour = 17;    // 17:00
$interval = 30;   // minutes per slot

// =====================================================
// GENERATE TIME SLOTS
// =====================================================

$slots = [];
$currentTime = strtotime("{$date} {$startHour}:00:00");
$endTime = strtotime("{$date} {$endHour}:00:00");

// Get calendar instance via Factory (uses CALENDAR_DRIVER from .env)
$calendar = CalendarFactory::create();

while ($currentTime < $endTime) {
    $timeStr = date('H:i', $currentTime);
    
    // Calculate slot boundaries
    $slotStart = date('Y-m-d H:i:s', $currentTime);
    $slotEnd = date('Y-m-d H:i:s', $currentTime + ($interval * 60));
    
    // Check availability using the configured calendar strategy
    $available = true;
    try {
        $available = $calendar->checkAvailability($slotStart, $slotEnd, $branchId);
    } catch (Exception $e) {
        // On API error, mark as unavailable for safety
        error_log("api_check_slots error: " . $e->getMessage());
        $available = false;
    }

    $slots[] = [
        'time' => $timeStr,
        'available' => $available
    ];
    
    $currentTime += ($interval * 60);
}

echo json_encode($slots);
