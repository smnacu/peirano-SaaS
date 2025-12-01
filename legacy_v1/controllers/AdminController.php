<?php
require_once __DIR__ . '/../src/Models/Appointment.php';

class AdminController {
    public function index() {
        Auth::requireRole('admin');
        $model = new Appointment();
        $branchId = $_GET['branch'] ?? 1;
        $date = $_GET['date'] ?? date('Y-m-d');

        $pending = $model->getPendingApprovals();
        $agenda = $model->getByBranchAndDate($branchId, $date);

        require __DIR__ . '/../templates/views/admin_dashboard.php';
    }

    public function approve() {
        Auth::requireRole('admin');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new Appointment())->updateStatus($_POST['id'], 'reserved');
            header("Location: index.php?route=admin&success=1");
        }
    }
}
?>