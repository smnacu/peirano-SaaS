<?php
/**
 * API Entry Point (Latent)
 * Future-proof structure for external integrations.
 * Disabled by default.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';

// 1. Security Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 2. Check Authorization
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized", "message" => "Missing Bearer Token"]);
    exit;
}

$token = $matches[1];
$pdo = Database::connect();

// Verify Token
$stmt = $pdo->prepare("SELECT id, company_name FROM users WHERE api_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(403);
    echo json_encode(["error" => "Forbidden", "message" => "Invalid Token"]);
    exit;
}

// 3. Simple Router
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Assuming /api/index.php is called directly or via rewrite. 
// If generic, we might need to parse relative to script name.

// Basic Endpoint: Get Appointments
if ($method === 'GET' && isset($_GET['resource']) && $_GET['resource'] === 'appointments') {
    $stmt = $pdo->prepare("SELECT id, start_time, vehicle_type FROM appointments WHERE user_id = ? LIMIT 50");
    $stmt->execute([$user['id']]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["data" => $data]);
    exit;
}

// Not Found
http_response_code(404);
echo json_encode(["error" => "Not Found", "message" => "Endpoint not implemented"]);
