<?php
/**
 * CRON SCRIPT - Reminders
 * Run every hour. Checks for appointments starting in 24 hours.
 * Usage: php scripts/cron_reminders.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Services/EmailService.php';

// CLI Protection
if (php_sapi_name() !== 'cli' && !isset($_GET['secret_key'])) {
    die("Access Denied");
}

echo "Starting Reminders...\n";

try {
    $pdo = Database::connect();
    
    // Select appts starting between 23.5 and 24.5 hours from now (approx 24h window) 
    // to match if cron runs hourly. 
    $sql = "SELECT a.id, a.start_time, u.email, u.company_name 
            FROM appointments a 
            JOIN users u ON a.user_id = u.id 
            WHERE a.start_time BETWEEN DATE_ADD(NOW(), INTERVAL 23 HOUR) AND DATE_ADD(NOW(), INTERVAL 25 HOUR)
            AND a.reminder_sent = 0 
            AND u.email IS NOT NULL 
            AND u.email != ''";

    $stmt = $pdo->query($sql);
    $appts = $stmt->fetchAll();
    
    echo "Found " . count($appts) . " reminders to send.\n";

    $emailService = new EmailService();

    foreach ($appts as $appt) {
        $sent = $emailService->sendStatusUpdate(
            $appt['email'], 
            $appt['company_name'], 
            'reminder', 
            $appt['start_time']
        );

        if ($sent) {
            $upd = $pdo->prepare("UPDATE appointments SET reminder_sent = 1 WHERE id = ?");
            $upd->execute([$appt['id']]);
            echo "Sent to {$appt['email']}\n";
        } else {
            echo "Failed to {$appt['email']}\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Done.\n";
