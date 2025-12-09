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
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Services/ReservationService.php';

use Services\ReservationService;

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
// GET TIME SLOTS FROM SERVICE
// =====================================================

try {
    $service = new ReservationService();
    $slots = $service->getAvailableSlots($date, $branchId);
    echo json_encode($slots);
} catch (Exception $e) {
    error_log("api_check_slots error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal Server Error']);
}
