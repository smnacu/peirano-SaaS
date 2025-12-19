<?php
/**
 * Reports & Analytics
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Database.php';

Auth::checkAdmin();
$pdo = Database::connect();
$pageTitle = 'Reportes y Métricas';

// --- DATA CALCULATION ---

// 1. Total Appointments by Month (Last 6 months)
$stmt = $pdo->query("
    SELECT DATE_FORMAT(start_time, '%Y-%m') as month, COUNT(*) as count 
    FROM appointments 
    WHERE start_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month 
    ORDER BY month ASC
");
$chartData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Top Absentees (Users with most 'absent' status)
$stmt = $pdo->query("
    SELECT u.company_name, COUNT(a.id) as absences 
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    WHERE a.attendance_status = 'absent'
    GROUP BY u.id
    ORDER BY absences DESC
    LIMIT 5
");
$topAbsentees = $stmt->fetchAll();

// 3. Peak Hours (Heuristic)
$stmt = $pdo->query("
    SELECT HOUR(start_time) as h, COUNT(*) as c 
    FROM appointments 
    GROUP BY h 
    ORDER BY c DESC 
    LIMIT 5
");
$peakHours = $stmt->fetchAll();

// --- EXPORT CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reporte_asistencia_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Fecha', 'Hora', 'Proveedor', 'Vehiculo', 'Carga', 'Asistencia']);
    
    $stmt = $pdo->query("
        SELECT a.id, DATE(a.start_time), TIME(a.start_time), u.company_name, a.vehicle_type, a.quantity, a.attendance_status 
        FROM appointments a 
        JOIN users u ON a.user_id = u.id 
        ORDER BY a.start_time DESC
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

require_once __DIR__ . '/templates/layouts/header.php';
require_once __DIR__ . '/templates/layouts/nav.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-bar-chart-line me-2"></i>Reportes y Métricas</h1>
        <a href="?export=csv" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Exportar Todo (CSV)</a>
    </div>

    <div class="row">
        <!-- KPI CARDS -->
        <div class="col-md-4 mb-4">
            <div class="card bg-primary text-white shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">Total Turnos (Mes)</h5>
                    <div class="display-4 fw-bold">
                        <?php 
                        $thisMonth = date('Y-m');
                        echo $chartData[$thisMonth] ?? 0; 
                        ?>
                    </div>
                    <small>Reservas este mes</small>
                </div>
            </div>
        </div>

        <!-- TOP ABSENTEES -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-danger text-white fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Mayores Inasistencias</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($topAbsentees as $abs): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo htmlspecialchars($abs['company_name']); ?>
                            <span class="badge bg-danger rounded-pill"><?php echo $abs['absences']; ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if(empty($topAbsentees)): ?>
                        <li class="list-group-item text-muted">Sin datos.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- PEAK HOURS -->
        <div class="col-md-4 mb-4">
             <div class="card shadow-sm h-100">
                <div class="card-header bg-info text-white fw-bold"><i class="bi bi-clock me-2"></i>Horas Pico</div>
                <div class="card-body">
                    <?php if(!empty($peakHours)): ?>
                        <?php foreach ($peakHours as $ph): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?php echo str_pad($ph['h'], 2, '0', STR_PAD_LEFT) . ':00'; ?></span>
                                <div class="progress w-75" style="height: 20px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo min(100, $ph['c'] * 10); ?>%">
                                        <?php echo $ph['c']; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Sin datos suficientes.</p>
                    <?php endif; ?>
                </div>
             </div>
        </div>
    </div>
    
    <!-- CHART (Simulated with simple CSS bars for vanilla PHP vibe) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-bold">Evolución de Turnos (Últimos 6 meses)</div>
        <div class="card-body">
            <div class="d-flex align-items-end" style="height: 200px; gap: 20px;">
                <?php 
                $maxVal = !empty($chartData) ? max($chartData) : 1;
                foreach ($chartData as $month => $count): 
                    $percent = ($count / $maxVal) * 100;
                ?>
                    <div class="text-center w-100">
                        <div class="bg-primary mx-auto rounded-top" style="height: <?php echo $percent; ?>%; width: 50%;" title="<?php echo $count; ?> turnos"></div>
                        <div class="mt-2 small text-muted"><?php echo $month; ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($chartData)): ?>
                    <p class="text-muted w-100 text-center align-self-center">Sin datos históricos.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>
