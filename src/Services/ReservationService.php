<?php
/**
 * Reservation Service
 * Handles business logic for appointments, including duration calculation,
 * availability checking, and database/calendar operations.
 *
 * @package WhiteLabel\Services
 */

declare(strict_types=1);

namespace Services;

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/Calendar/CalendarFactory.php';

use Database;
use PDO;
use Exception;
use Services\Calendar\CalendarFactory;

class ReservationService
{
    private PDO $pdo;

    // Slot Configuration
    // TODO: These could be moved to a configuration file or database settings
    public const START_HOUR = 8;
    public const END_HOUR = 17;
    public const SLOT_INTERVAL = 30; // minutes

    public function __construct()
    {
        $this->pdo = Database::connect();
    }

    public function calculateDuration(string $vehicleType, ?int $userDuration = null): array
    {
        $blockMinutes = 60;
        $realMinutes = 55;

        // 1. User Override (Priority)
        // If user has a specific duration set (e.g. 120 min), we use that regardless of vehicle.
        if ($userDuration !== null && $userDuration > 0) {
            $blockMinutes = $userDuration;
            $realMinutes = max(15, $userDuration - 5); // Ensure at least 15 min real time? Or just -5
            return ['blockMinutes' => $blockMinutes, 'realMinutes' => $realMinutes];
        }

        // 2. Vehicle Type Lookup
        // Try to find in DB (cached check ideally, but simple query for now)
        try {
            $stmt = $this->pdo->prepare("SELECT block_minutes, real_minutes FROM vehicle_types WHERE name = ? AND active = 1");
            $stmt->execute([$vehicleType]);
            $vInfo = $stmt->fetch();
            if ($vInfo) {
                return [
                    'blockMinutes' => (int)$vInfo['block_minutes'],
                    'realMinutes' => (int)$vInfo['real_minutes']
                ];
            }
        } catch (Exception $e) {
            // Fallback
        }

        // 3. Fallback Hardcoded (Maintenance)
        if ($vehicleType === 'Utilitario' || str_contains($vehicleType, 'Utilitario')) {
            $blockMinutes = 30;
            $realMinutes = 25;
        }

        return [
            'blockMinutes' => $blockMinutes,
            'realMinutes' => $realMinutes
        ];
    }

    /**
     * Get available time slots for a specific date and branch.
     *
     * @param string $date Y-m-d
     * @param int $branchId
     * @return array List of slots with time and availability status
     */
    public function getAvailableSlots(string $date, int $branchId): array
    {
        $slots = [];
        $currentTime = strtotime("{$date} " . self::START_HOUR . ":00:00");
        $endTime = strtotime("{$date} " . self::END_HOUR . ":00:00");

        $calendar = CalendarFactory::create();

        while ($currentTime < $endTime) {
            $timeStr = date('H:i', $currentTime);

            // Calculate slot boundaries
            $slotStart = date('Y-m-d H:i:s', $currentTime);
            $slotEnd = date('Y-m-d H:i:s', $currentTime + (self::SLOT_INTERVAL * 60));

            // Check availability
            $available = true;
            try {
                $available = $calendar->checkAvailability($slotStart, $slotEnd, $branchId);
            } catch (Exception $e) {
                error_log("ReservationService::getAvailableSlots error: " . $e->getMessage());
                $available = false;
            }

            $slots[] = [
                'time' => $timeStr,
                'available' => $available
            ];

            $currentTime += (self::SLOT_INTERVAL * 60);
        }

        return $slots;
    }

    /**
     * Create a new reservation.
     *
     * @param array $data Reservation data
     * @param array $user User session data
     * @return int The ID of the created appointment
     * @throws Exception If validation fails or database error occurs
     */
    public function createReservation(array $data, array $user): int
    {
        // validate required fields
        $required = ['branch_id', 'date', 'time', 'vehicle_type', 'quantity', 'driver_name', 'driver_dni'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Faltan datos obligatorios: {$field}");
            }
        }

        if ($data['quantity'] < 1) {
            throw new Exception("La cantidad debe ser mayor a 0.");
        }

        if (!empty($data['needs_helper']) && (empty($data['helper_name']) || empty($data['helper_dni']))) {
            throw new Exception("Datos del peón requeridos.");
        }

        // Calculate Times
        $startTime = $data['date'] . ' ' . $data['time'] . ':00';
        $userDuration = isset($user['default_duration']) ? (int)$user['default_duration'] : null;
        $durations = $this->calculateDuration($data['vehicle_type'], $userDuration);

        $checkEndTime = date('Y-m-d H:i:s', strtotime($startTime) + ($durations['blockMinutes'] * 60));
        $eventEndTime = date('Y-m-d H:i:s', strtotime($startTime) + ($durations['realMinutes'] * 60));

        try {
            // TRANSACTION START
            $this->pdo->beginTransaction();

            // CONCURRENCY LOCK: Lock the branch row to serialize bookings for this branch (Heavy but safe for avoiding overlaps)
            // Or better: Lock specific time range? Harder. Branch locking is safest for MVP concurrency.
            $stmtLock = $this->pdo->prepare("SELECT id FROM branches WHERE id = ? FOR UPDATE");
            $stmtLock->execute([$data['branch_id']]);

            // Check Availability (Inside Transaction)
            $calendar = CalendarFactory::create();
            // We need to re-instantiate or pass PDO to calendar factory to ensure it uses the same transaction connection?
            // Factory creates new PDO? Check Factory. If it creates new PDO, transaction won't work across.
            // Assumption: CalendarFactory uses Database::connect() which returns the *same* singleton PDO instance usually?
            // Checking Database class later. If it returns same instance, we are good.

            // Re-check inside lock
            if ($durations['blockMinutes'] >= 15) {
                if (!$calendar->checkAvailability($startTime, $checkEndTime, (int)$data['branch_id'])) {
                    $this->pdo->rollBack();
                    throw new Exception("El horario seleccionado acaba de ser ocupado. Por favor elija otro.");
                }
            }

            // Get branch name (optional, but needed for calendar event)
            $branchName = $this->getBranchName((int)$data['branch_id']);

            // Insert Appointment
            $stmt = $this->pdo->prepare("
                INSERT INTO appointments (branch_id, user_id, vehicle_type, start_time, end_time, quantity, needs_forklift, needs_helper, driver_name, driver_dni, helper_name, helper_dni, observations, attendance_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");

            $stmt->execute([
                $data['branch_id'],
                $user['id'],
                $data['vehicle_type'],
                $startTime,
                $eventEndTime,
                $data['quantity'],
                isset($data['needs_forklift']) ? 1 : 0,
                isset($data['needs_helper']) ? 1 : 0,
                $data['driver_name'],
                $data['driver_dni'],
                $data['helper_name'] ?? null,
                $data['helper_dni'] ?? null,
                $data['observations'] ?? '',
            ]);

            $appointmentId = (int) $this->pdo->lastInsertId();

            // COMMIT
            $this->pdo->commit();

            // SEND EMAIL NOTIFICATION (Outside transaction to not block DB if mail is slow)
            try {
                if (!empty($_SESSION['user']['email'])) {
                    require_once __DIR__ . '/EmailService.php';
                    $emailService = new \EmailService();
                    $emailService->sendStatusUpdate($_SESSION['user']['email'], $user['name'], 'reserved', $startTime);
                }
            } catch (Exception $e) {
                error_log("Email Error: " . $e->getMessage());
            }

            // Create Calendar Event
            $description = "Proveedor: {$user['name']}\n";
            $description .= "Vehículo: {$data['vehicle_type']}\n";
            $description .= "Bultos: {$data['quantity']}\n";
            $description .= "Sucursal: {$branchName}\n";
            $description .= "Chofer: {$data['driver_name']} (DNI: {$data['driver_dni']})";

            if (!empty($data['needs_helper'])) {
                $description .= "\nPeón: {$data['helper_name']} (DNI: {$data['helper_dni']})";
            }

            if (!empty($data['needs_forklift'])) {
                $description .= "\n⚠️ Requiere Autoelevador";
            }

            $subject = "Turno ({$branchName}): {$user['name']}";

            $eventId = $calendar->createEvent(
                $subject,
                $startTime,
                $eventEndTime,
                $description,
                $branchName
            );

            // Update with event ID
            if ($eventId) {
                $update = $this->pdo->prepare("UPDATE appointments SET outlook_event_id = ? WHERE id = ?");
                $update->execute([$eventId, $appointmentId]);
            }

            return $appointmentId;

        } catch (PDOException $e) {
            error_log("ReservationService DB Error: " . $e->getMessage());
            throw new Exception("Error al guardar la reserva en base de datos.");
        }
    }

    private function getBranchName(int $branchId): string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM branches WHERE id = ?");
            $stmt->execute([$branchId]);
            return $stmt->fetchColumn() ?: 'Sucursal';
        } catch (Exception $e) {
            return 'Sucursal';
        }
    }
}
