<?php
// setup_integrations.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/branding.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Utils.php';
require_once __DIR__ . '/src/Services/IntegrationService.php';

// Verify Admin Role
Auth::requireRole(['admin']);

$integrationService = new IntegrationService();
$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Utils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad (CSRF).";
    } else {
        try {
            $data = [
                'provider' => $_POST['provider'],
                'google_client_id' => $_POST['google_client_id'] ?? null,
                'google_client_secret' => $_POST['google_client_secret'] ?? null,
                'ms_client_id' => $_POST['ms_client_id'] ?? null,
                'ms_client_secret' => $_POST['ms_client_secret'] ?? null,
                'ms_tenant_id' => $_POST['ms_tenant_id'] ?? null,
            ];
            
            $integrationService->saveIntegrationConfig($data);
            $message = "Configuración guardada exitosamente.";
        } catch (Exception $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

// Get Current Config
$config = $integrationService->getIntegrationConfig();
$currentProvider = $config['provider'];

$pageTitle = 'Configuración de Integraciones';
require_once __DIR__ . '/templates/layouts/header.php';
require_once __DIR__ . '/templates/layouts/nav.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Configuración de Integraciones</h2>
        <a href="admin.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Sidebar / Provider Selection Info -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white fw-bold">
                    <i class="bi bi-info-circle me-2"></i>Información
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Seleccione el proveedor de calendario que desea utilizar para espejar los turnos del sistema.
                    </p>
                    <div class="alert alert-info small">
                        <strong>Nota:</strong> El sistema siempre mantendrá un registro local de los turnos ("Principal"). La integración externa funcionará como un espejo para su comodidad.
                    </div>
                    <hr>
                    <h6 class="fw-bold text-dark">Proveedores Soportados:</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><i class="bi bi-microsoft me-2 text-primary"></i>Microsoft (Outlook/Hotmail)</li>
                        <li class="mb-2"><i class="bi bi-building me-2 text-primary"></i>Microsoft Graph (Empresas)</li>
                        <li class="mb-2"><i class="bi bi-google me-2 text-success"></i>Google Calendar</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Configuration Form -->
        <div class="col-md-8">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4">
                    <form method="POST" id="configForm">
                        <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">

                        <!-- Provider Selector -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Proveedor de Calendario</label>
                            <select name="provider" id="providerSelect" class="form-select form-select-lg border-primary">
                                <option value="local" <?php echo $currentProvider === 'local' ? 'selected' : ''; ?>>Solo Local (Sin integración)</option>
                                <option value="google" <?php echo $currentProvider === 'google' ? 'selected' : ''; ?>>Google Calendar</option>
                                <option value="microsoft_personal" <?php echo $currentProvider === 'microsoft_personal' ? 'selected' : ''; ?>>Microsoft Personal (Outlook/Live)</option>
                                <option value="microsoft_graph" <?php echo $currentProvider === 'microsoft_graph' ? 'selected' : ''; ?>>Microsoft Graph (Empresas/Azure AD)</option>
                            </select>
                        </div>

                        <!-- Google Config -->
                        <div id="section-google" class="provider-section d-none">
                            <h5 class="text-success border-bottom pb-2 mb-3"><i class="bi bi-google me-2"></i>Configuración Google</h5>
                            <div class="mb-3">
                                <label class="form-label">Client ID</label>
                                <input type="text" name="google_client_id" class="form-control" value="<?php echo htmlspecialchars($config['google_client_id'] ?? ''); ?>">
                                <div class="form-text">Obtenido de Google Cloud Console (OAuth 2.0 Client IDs).</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Client Secret</label>
                                <input type="text" name="google_client_secret" class="form-control" value="<?php echo htmlspecialchars($config['google_client_secret'] ?? ''); ?>">
                            </div>
                            <div class="alert alert-warning small">
                                <strong>Callback URI:</strong> <code class="user-select-all"><?php echo BASE_URL; ?>callback_google.php</code>
                            </div>
                        </div>

                        <!-- Microsoft Config -->
                        <div id="section-microsoft" class="provider-section d-none">
                            <h5 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-microsoft me-2"></i>Configuración Microsoft</h5>
                            <div class="mb-3">
                                <label class="form-label">Application (Client) ID</label>
                                <input type="text" name="ms_client_id" class="form-control" value="<?php echo htmlspecialchars($config['ms_client_id'] ?? ''); ?>">
                                <div class="form-text">Obtenido de Azure App Registrations.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Client Secret</label>
                                <input type="text" name="ms_client_secret" class="form-control" value="<?php echo htmlspecialchars($config['ms_client_secret'] ?? ''); ?>">
                            </div>
                            
                            <!-- Tenant ID only for Graph/Enterprise -->
                            <div id="field-tenant" class="mb-3">
                                <label class="form-label">Directory (Tenant) ID</label>
                                <input type="text" name="ms_tenant_id" class="form-control" value="<?php echo htmlspecialchars($config['ms_tenant_id'] ?? 'common'); ?>">
                                <div class="form-text">Use <code>common</code> para cuentas personales o multitenant.</div>
                            </div>

                            <div class="alert alert-warning small">
                                <strong>Redirect URI:</strong> <code class="user-select-all"><?php echo BASE_URL; ?>callback_ms.php</code>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="bi bi-save me-2"></i>Guardar Configuración</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const providerSelect = document.getElementById('providerSelect');
        const sections = document.querySelectorAll('.provider-section');
        const fieldTenant = document.getElementById('field-tenant');

        function updateVisibility() {
            const val = providerSelect.value;
            
            // Convert specific ms types to generic 'microsoft' for showing section
            let sectionId = '';
            if (val === 'google') sectionId = 'section-google';
            if (val.startsWith('microsoft')) sectionId = 'section-microsoft';

            // Show proper section
            sections.forEach(el => el.classList.add('d-none'));
            if (sectionId) {
                document.getElementById(sectionId).classList.remove('d-none');
            }

            // Tenant specific logic
            if (val === 'microsoft_personal') {
                if(fieldTenant) fieldTenant.style.display = 'none'; // Hide for personal usually, or auto-set common
            } else {
                if(fieldTenant) fieldTenant.style.display = 'block';
            }
        }

        providerSelect.addEventListener('change', updateVisibility);
        updateVisibility(); // Init
    });
</script>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>
