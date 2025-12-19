<?php
// callback_ms.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Services/IntegrationService.php';

Auth::requireRole('admin');

if (isset($_GET['error'])) {
    die("Error de Microsoft: " . htmlspecialchars($_GET['error_description'] ?? $_GET['error']));
}

if (!isset($_GET['code'])) {
    die("Error: No se recibió código de autorización.");
}

$code = $_GET['code'];
$integrationService = new IntegrationService();
$config = $integrationService->getIntegrationConfig();

// Token Endpoint
$tenant = $config['ms_tenant_id'] ?: 'common';
$url = "https://login.microsoftonline.com/$tenant/oauth2/v2.0/token";

$params = [
    'client_id' => $config['ms_client_id'],
    'scope' => 'Calendars.ReadWrite offline_access User.Read',
    'code' => $code,
    'redirect_uri' => BASE_URL . "callback_ms.php",
    'grant_type' => 'authorization_code',
    'client_secret' => $config['ms_client_secret'],
];

// Curl Request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Caution in prod

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode === 200 && isset($data['refresh_token'])) {
    // Success! Save Refresh Token
    $integrationService->setSetting('ms_refresh_token', $data['refresh_token']);
    
    // Optional: Save Access Token too if we want (expire in 1h), but refresh token is key.
    
    header("Location: setup_integrations.php?status=connected");
    exit;
} else {
    echo "<h1>Error conectando con Microsoft</h1>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    echo "<br><a href='setup_integrations.php'>Volver</a>";
}
