<?php
// api_check_slots.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Services/OutlookSync.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$date = $_GET['date'] ?? '';
$branch_id = $_GET['branch_id'] ?? '';

if (!$date || !$branch_id) {
    echo json_encode([]);
    exit;
}

// Generar slots de 8:00 a 17:00 cada 30 min (ejemplo simple)
// En un caso real, esto vendría de configuración de la sucursal
$startHour = 8;
$endHour = 17;
$interval = 30; // minutos

$slots = [];
$currentTime = strtotime("$date $startHour:00:00");
$endTime = strtotime("$date $endHour:00:00");

$outlook = new OutlookSync();

while ($currentTime < $endTime) {
    $timeStr = date('H:i', $currentTime);
    
    // Verificar disponibilidad real con OutlookSync
    // Asumimos bloques de 30 min por defecto para la visualización
    $slotStart = date('Y-m-d H:i:s', $currentTime);
    $slotEnd = date('Y-m-d H:i:s', $currentTime + ($interval * 60));
    
    $available = true;
    try {
        $available = $outlook->checkAvailability($slotStart, $slotEnd, $branch_id);
    } catch (Exception $e) {
        // Si falla la API, asumimos ocupado por seguridad o logueamos
        $available = false; 
    }

    $slots[] = [
        'time' => $timeStr,
        'available' => $available
    ];
    
    $currentTime += ($interval * 60);
}

echo json_encode($slots);
?>
