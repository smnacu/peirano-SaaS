<?php
// src/Models/Appointment.php
require_once __DIR__ . '/../Database.php';

class Appointment {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
    }

    // Para Admin y Operario: Ver agenda del día
    public function getByBranchAndDate($branchId, $date) {
        $sql = "SELECT a.*, u.company_name, u.cuit 
                FROM appointments a
                JOIN users u ON a.user_id = u.id
                WHERE a.branch_id = ? AND DATE(a.start_time) = ?
                AND a.status != 'cancelled'
                ORDER BY a.start_time ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$branchId, $date]);
        return $stmt->fetchAll();
    }

    // Para Admin: Ver pendientes de aprobación
    public function getPendingApprovals() {
        $sql = "SELECT a.*, u.company_name, b.name as branch_name 
                FROM appointments a 
                JOIN users u ON a.user_id = u.id 
                JOIN branches b ON a.branch_id = b.id
                WHERE a.status = 'pending_approval' 
                ORDER BY a.start_time ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    // Cambiar estado (Aprobar, Rechazar, Logística)
    public function updateStatus($id, $status) {
        $sql = "UPDATE appointments SET status = ? WHERE id = ?";
        return $this->pdo->prepare($sql)->execute([$status, $id]);
    }

    // Lógica Logística (Check-in/Out)
    public function updateLogisticsStatus($id, $action) {
        $sql = "";
        switch ($action) {
            case 'arrive': $sql = "UPDATE appointments SET status='arrived', arrived_at=NOW() WHERE id=?"; break;
            case 'enter': $sql = "UPDATE appointments SET status='in_progress', entered_at=NOW() WHERE id=?"; break;
            case 'complete': 
                $sql = "UPDATE appointments SET status='completed', exited_at=NOW(), real_duration=TIMESTAMPDIFF(MINUTE, entered_at, NOW()) WHERE id=?"; 
                break;
        }
        return $sql ? $this->pdo->prepare($sql)->execute([$id]) : false;
    }

    // Crear Turno (Con validación anti-colisión)
    public function create($data) {
        $this->pdo->beginTransaction();
        try {
            // Validar si ya está ocupado
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM appointments 
                                         WHERE branch_id = ? AND status NOT IN ('cancelled', 'rejected')
                                         AND start_time < ? AND end_time > ?");
            $stmt->execute([$data['branch_id'], $data['end'], $data['start']]);
            
            if ($stmt->fetchColumn() > 0) throw new Exception("Horario ya ocupado.");

            $sql = "INSERT INTO appointments (user_id, branch_id, start_time, end_time, vehicle_type, quantity, driver_name, driver_dni, request_recurring, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['user_id'], $data['branch_id'], $data['start'], $data['end'], 
                $data['vehicle'], $data['qty'], $data['driver'], $data['dni'], 
                $data['recurring'], $data['status']
            ]);
            
            $id = $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
?>