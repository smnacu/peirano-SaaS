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

    /**
     * Calculate reservation duration based on vehicle type and user preferences.
     *
     * @param string $vehicleType
     * @param int $userDuration Default user duration (usually 60)
     * @return array{blockMinutes: int, realMinutes: int}
     */
    public function calculateDuration(string $vehicleType, int $userDuration = 60): array
    {
        $blockMinutes = 60;
        $realMinutes = 55; // 5 min buffer

        if ($userDuration < 15) {
            $blockMinutes = $userDuration;
            $realMinutes = $userDuration;
        } else {
            // Shorter blocks for smaller vehicles
            if ($vehicleType === 'Utilitario') {
                $blockMinutes = 30;
                $realMinutes = 25; // 5 min buffer
            } else {
                $blockMinutes = 60;
                $realMinutes = 55; // 5 min buffer
            }

            // Custom duration overrides defaults
            if ($userDuration !== 60) {
                $blockMinutes = $userDuration;
                $realMinutes = $userDuration - 5;
            }
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
        $durations = $this->calculateDuration($data['vehicle_type']); // Assuming default 60 for user duration for now

        $checkEndTime = date('Y-m-d H:i:s', strtotime($startTime) + ($durations['blockMinutes'] * 60));
        $eventEndTime = date('Y-m-d H:i:s', strtotime($startTime) + ($durations['realMinutes'] * 60));

        // Check Availability
        $calendar = CalendarFactory::create();
        if ($durations['blockMinutes'] >= 15) {
            if (!$calendar->checkAvailability($startTime, $checkEndTime, (int)$data['branch_id'])) {
                throw new Exception("El horario seleccionado ya no está disponible.");
            }
        }

        try {
            // Get branch name (optional, but needed for calendar event)
            $branchName = $this->getBranchName((int)$data['branch_id']);

            // Insert into Database
            $sql = "INSERT INTO appointments
                    (user_id, branch_id, start_time, end_time, vehicle_type,
                        needs_forklift, needs_helper, quantity, observations,
                        driver_name, driver_dni, helper_name, helper_dni)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $user['id'],
                $data['branch_id'],
                $startTime,
                $eventEndTime,
                $data['vehicle_type'],
                !empty($data['needs_forklift']) ? 1 : 0,
                !empty($data['needs_helper']) ? 1 : 0,
                $data['quantity'],
                $data['observations'] ?? '',
                $data['driver_name'],
                $data['driver_dni'],
                $data['helper_name'] ?? '',
                $data['helper_dni'] ?? ''
            ]);

            $appointmentId = (int) $this->pdo->lastInsertId();

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
