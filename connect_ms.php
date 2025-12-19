<?php
// connect_ms.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Services/IntegrationService.php';

Auth::requireRole('admin');

$integrationService = new IntegrationService();
$config = $integrationService->getIntegrationConfig();

if (empty($config['ms_client_id'])) {
    die("Error: Faltan configurar Client ID y Secret en el panel.");
}

$tenant = $config['ms_tenant_id'] ?: 'common';
$client_id = $config['ms_client_id'];
$redirect_uri = BASE_URL . "callback_ms.php";
$scope = "Calendars.ReadWrite offline_access User.Read"; // Permissions needed

// Authorization URL
$url = "https://login.microsoftonline.com/$tenant/oauth2/v2.0/authorize?";
$url .= "client_id=" . $client_id;
$url .= "&response_type=code";
$url .= "&redirect_uri=" . urlencode($redirect_uri);
$url .= "&response_mode=query";
$url .= "&scope=" . urlencode($scope);
$url .= "&state=" . Utils::generateCsrfToken(); // Using token as state for simplicity

// Action Handler
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'disconnect') {
        // Just clear the token from DB
        $integrationService->setSetting('ms_refresh_token', null);
        header("Location: setup_integrations.php?status=disconnected");
        exit;
    }
}

// Redirect to Microsoft
header("Location: " . $url);
exit;
