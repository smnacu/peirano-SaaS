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
    // Handle Email Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_email') {
        if (!Utils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $error = "Error de seguridad (CSRF).";
        } else {
             try {
                $integrationService->saveEmailConfig($_POST);
                $message = "Configuración de correo guardada.";
            } catch (Exception $e) {
                $error = "Error al guardar correo: " . $e->getMessage();
            }
        }
    }

    // Handle Integration Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_integration') {
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
                $message = "Configuración de integraciones guardada.";
            } catch (Exception $e) {
                $error = "Error al guardar integración: " . $e->getMessage();
            }
        }
    }

    // Get Current Config
    $config = $integrationService->getIntegrationConfig();
    $currentProvider = $config['provider'];
    
    // Auth / Redirect URLs
    $msRedirectUrl = BASE_URL . "callback_ms.php";

    $pageTitle = 'Configuración de Integraciones y Correo';
    require_once __DIR__ . '/templates/layouts/header.php';
    require_once __DIR__ . '/templates/layouts/nav.php';
    ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Integraciones y Correo</h2>
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
            
            <!-- SECTION 1: EMAIL (SMTP) -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-lg border-0 h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-envelope-fill me-2"></i>Envío de Correos (SMTP)</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Configure las credenciales para el envío de notificaciones. Se recomienda usar cuentas dedicadas.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="action" value="save_email">
                            <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="smtp_enabled" name="smtp_enabled" <?php echo $config['smtp_enabled'] == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="smtp_enabled">Habilitar SMTP Personalizado</label>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-8 mb-2">
                                    <label class="form-label">Servidor SMTP</label>
                                    <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($config['smtp_host']); ?>" placeholder="smtp.gmail.com">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Puerto</label>
                                    <input type="number" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($config['smtp_port']); ?>" placeholder="587">
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Usuario / Correo</label>
                                <input type="email" name="smtp_user" class="form-control" value="<?php echo htmlspecialchars($config['smtp_user']); ?>" placeholder="ejemplo@empresa.com">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Contraseña (App Password)</label>
                                <input type="password" name="smtp_pass" class="form-control" value="<?php echo htmlspecialchars($config['smtp_pass']); ?>" placeholder="••••••••">
                                <div class="form-text">Si usa Gmail/Outlook, genere una "Contraseña de Aplicación".</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Nombre del Remitente</label>
                                <input type="text" name="smtp_from_name" class="form-control" value="<?php echo htmlspecialchars($config['smtp_from_name']); ?>" placeholder="Peirano Turnos">
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="bi bi-save me-2"></i>Guardar Configuración SMTP</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: CALENDAR INTEGRATION -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-lg border-0 h-100">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Sincronización de Calendario</h5>
                    </div>
                    <div class="card-body">
                         <p class="text-muted small mb-3">
                            Seleccione dónde se agendarán los turnos. Para Microsoft corporativo, requiere Azure App Registration.
                        </p>
                        
                        <form method="POST" id="configForm">
                            <input type="hidden" name="action" value="save_integration">
                            <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
    
                            <!-- Provider Selector -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Proveedor de Calendario</label>
                                <select name="provider" id="providerSelect" class="form-select border-primary" onchange="updateVisibility()">
                                    <option value="local" <?php echo $currentProvider === 'local' ? 'selected' : ''; ?>>Solo Local (Sin integración)</option>
                                    <option value="google" <?php echo $currentProvider === 'google' ? 'selected' : ''; ?>>Google Calendar</option>
                                    <option value="microsoft_graph" <?php echo $currentProvider === 'microsoft_graph' ? 'selected' : ''; ?>>Microsoft Graph (Empresas/Outlook)</option>
                                </select>
                            </div>
    
                            <!-- Google Config -->
                            <div id="section-google" class="provider-section d-none">
                                <div class="alert alert-warning small">
                                    <strong>Callback URI:</strong> <code class="user-select-all"><?php echo BASE_URL; ?>callback_google.php</code>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Client ID</label>
                                    <input type="text" name="google_client_id" class="form-control form-control-sm" value="<?php echo htmlspecialchars($config['google_client_id'] ?? ''); ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Client Secret</label>
                                    <input type="text" name="google_client_secret" class="form-control form-control-sm" value="<?php echo htmlspecialchars($config['google_client_secret'] ?? ''); ?>">
                                </div>
                            </div>
    
                            <!-- Microsoft Config -->
                            <div id="section-microsoft" class="provider-section d-none">
                                 <div class="alert alert-warning small py-2">
                                    <strong>Redirect URI:</strong> <code class="user-select-all"><?php echo $msRedirectUrl; ?></code>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Application (Client) ID</label>
                                    <input type="text" name="ms_client_id" class="form-control form-control-sm" value="<?php echo htmlspecialchars($config['ms_client_id'] ?? ''); ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Client Secret (Value)</label>
                                    <input type="text" name="ms_client_secret" class="form-control form-control-sm" value="<?php echo htmlspecialchars($config['ms_client_secret'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Directory (Tenant) ID</label>
                                    <input type="text" name="ms_tenant_id" class="form-control form-control-sm" value="<?php echo htmlspecialchars($config['ms_tenant_id'] ?? 'common'); ?>">
                                </div>
                                
                                <!-- CONNECTION STATUS / ACTION -->
                                <hr>
                                <div class="text-center">
                                    <?php if (!empty($config['ms_refresh_token'])): ?>
                                        <div class="text-success mb-2 fw-bold"><i class="bi bi-link-45deg"></i> Cuenta Conectada</div>
                                        <a href="connect_ms.php?action=disconnect" class="btn btn-sm btn-outline-danger">Desconectar</a>
                                    <?php else: ?>
                                        <a href="connect_ms.php?action=connect" target="_blank" class="btn btn-dark w-100">
                                            <i class="bi bi-microsoft me-2"></i>Conectar Cuenta Microsoft
                                        </a>
                                        <div class="form-text text-center small mt-1">Se abrirá una ventana para dar permisos.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
    
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-secondary fw-bold">Guardar Configuración</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function updateVisibility() {
            const val = document.getElementById('providerSelect').value;
            document.querySelectorAll('.provider-section').forEach(el => el.classList.add('d-none'));
            if (val === 'google') document.getElementById('section-google').classList.remove('d-none');
            if (val.startsWith('microsoft')) document.getElementById('section-microsoft').classList.remove('d-none');
        }
        document.addEventListener('DOMContentLoaded', updateVisibility);
    </script>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>
