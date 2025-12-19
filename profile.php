<?php
/**
 * User Profile & Security Settings
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Utils.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Services/TwoFactorService.php';

Auth::check(); // Allow any logged in user (Admin or Provider)

$pdo = Database::connect();
$userId = $_SESSION['user']['id'];
$message = '';
$error = '';
$show2FASetup = false;
$newSecret = null;
$qrUrl = null;

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Utils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad (CSRF).";
    } else {
        $action = $_POST['action'];

        // Change Password
        if ($action === 'change_password') {
            $current = $_POST['current_password'];
            $new = $_POST['new_password'];
            $confirm = $_POST['confirm_password'];

            // Verify current
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($current, $hash)) {
                $error = "La contraseña actual es incorrecta.";
            } elseif ($new !== $confirm) {
                $error = "Las nuevas contraseñas no coinciden.";
            } else {
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$newHash, $userId]);
                $message = "Contraseña actualizada correctamente.";
            }
        }

        // Enable 2FA - Step 1: Generate
        if ($action === 'enable_2fa_init') {
            $tfa = new TwoFactorService();
            $newSecret = $tfa->generateSecret();
            $show2FASetup = true;
            $qrUrl = $tfa->getProvisioningUri(brand('name'), $_SESSION['user']['cuit'], $newSecret);
        }

        // Enable 2FA - Step 2: Confirm
        if ($action === 'enable_2fa_confirm') {
            $secret = $_POST['secret'];
            $code = $_POST['code'];
            $tfa = new TwoFactorService();
            
            if ($tfa->verifyCode($secret, $code)) {
                $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1 WHERE id = ?");
                $stmt->execute([$secret, $userId]);
                $message = "¡Doble Factor (2FA) activado con éxito!";
                $show2FASetup = false;
            } else {
                $error = "El código ingresado es incorrecto. Intente nuevamente.";
                // Re-show setup
                $newSecret = $secret;
                $show2FASetup = true;
                $qrUrl = $tfa->getProvisioningUri(brand('name'), $_SESSION['user']['cuit'], $newSecret);
            }
        }

        // Disable 2FA
        if ($action === 'disable_2fa') {
             // For security, ideally ask for password again. Skipping for MVP.
             $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
             $stmt->execute([$userId]);
             $message = "2FA desactivado.";
        }

        // Cancel Appointment
        if ($action === 'cancel_appointment') {
            $apptId = $_POST['appointment_id'];
            
            // Fetch Appointment to verify ownership and time
            $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ?");
            $stmt->execute([$apptId, $userId]);
            $appt = $stmt->fetch();

            if ($appt) {
                // Check 24h rule
                $startTime = strtotime($appt['start_time']);
                $limitTime = time() + (24 * 60 * 60); // Now + 24h

                if ($startTime > $limitTime) {
                    // OK to cancel
                    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
                    $stmt->execute([$apptId]);
                    
                    // Notify
                     require_once __DIR__ . '/src/Services/EmailService.php';
                     (new EmailService())->sendStatusUpdate($_SESSION['user']['email'] ?? 'unknown', $_SESSION['user']['name'], 'cancelled', $appt['start_time']);

                    $message = "Turno cancelado correctamente.";
                } else {
                    $error = "Solo se pueden cancelar turnos con más de 24 horas de anticipación.";
                }
            } else {
                $error = "Turno no encontrado.";
            }
        }
    }
}

// Fetch User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Fetch Appointment History
$stmt = $pdo->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY start_time DESC LIMIT 50");
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

require_once __DIR__ . '/templates/layouts/header.php';
require_once __DIR__ . '/templates/layouts/nav.php';
?>

<div class="container mt-5">
    <div class="row">
        <!-- Sidebar / User Info -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="avatar-placeholder rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
                            <?php echo strtoupper(substr($currentUser['company_name'], 0, 1)); ?>
                        </div>
                    </div>
                    <h5 class="card-title"><?php echo htmlspecialchars($currentUser['company_name']); ?></h5>
                    <p class="text-muted mb-1">CUIT: <?php echo htmlspecialchars($currentUser['cuit']); ?></p>
                    <p class="text-muted small"><?php echo htmlspecialchars($currentUser['email'] ?? 'Sin email'); ?></p>
                </div>
            </div>

            <!-- Security Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <i class="bi bi-shield-lock me-2"></i>Seguridad
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Change Password -->
                    <h6 class="fw-bold">Cambiar Contraseña</h6>
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
                        
                        <div class="mb-2">
                            <input type="password" name="current_password" class="form-control form-control-sm" placeholder="Contraseña Actual" required>
                        </div>
                        <div class="mb-2">
                            <input type="password" name="new_password" class="form-control form-control-sm" placeholder="Nueva Contraseña" required>
                        </div>
                        <div class="mb-2">
                            <input type="password" name="confirm_password" class="form-control form-control-sm" placeholder="Repetir Nueva" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-sm">Actualizar</button>
                        </div>
                    </form>

                    <hr>

                    <!-- 2FA Section -->
                    <h6 class="fw-bold">Doble Factor de Autenticación (2FA)</h6>
                    
                    <?php if ($currentUser['two_factor_enabled']): ?>
                        <div class="alert alert-success py-2 small">
                            <i class="bi bi-check-circle-fill me-1"></i> Activado
                        </div>
                        <form method="POST" onsubmit="return confirm('¿Seguro que querés desactivar 2FA?');">
                            <input type="hidden" name="action" value="disable_2fa">
                            <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Desactivar 2FA</button>
                        </form>
                    <?php else: ?>
                        <p class="small text-muted">Aumentá la seguridad de tu cuenta solicitando un código desde tu celular al iniciar sesión.</p>
                        
                        <?php if (!$show2FASetup): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="enable_2fa_init">
                                <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
                                <button type="submit" class="btn btn-outline-primary btn-sm w-100">Configurar 2FA</button>
                            </form>
                        <?php else: ?>
                            <!-- 2FA Setup Flow -->
                            <div class="bg-light p-2 rounded border mb-2">
                                <p class="small mb-2 fw-bold">1. Escaneá el código QR con Google Authenticator:</p>
                                <div class="text-center mb-2">
                                    <!-- Using QR Server API for simplicity in MVP -->
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($qrUrl); ?>" alt="QR Code" class="img-fluid border p-1 bg-white">
                                </div>
                                <p class="small mb-2 fw-bold">2. Ingresá el código generado:</p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="enable_2fa_confirm">
                                    <input type="hidden" name="secret" value="<?php echo $newSecret; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
                                    
                                    <div class="input-group mb-2">
                                        <input type="text" name="code" class="form-control form-control-sm text-center" placeholder="123456" pattern="[0-9]*" inputmode="numeric" required autocomplete="one-time-code">
                                        <button type="submit" class="btn btn-success btn-sm">Verificar</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- Main Content / History -->
        <div class="col-md-8">
            <h4 class="mb-3">Historial de Turnos</h4>
             <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Horario</th>
                                    <th>Vehículo</th>
                                    <th>Estado / Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($history) > 0): ?>
                                    <?php foreach ($history as $h): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($h['start_time'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($h['start_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($h['vehicle_type']); ?></td>
                                            <td>
                                                <?php 
                                                // Status Logic
                                                if ($h['status'] === 'cancelled'): ?>
                                                    <span class="badge bg-secondary">Cancelado</span>
                                                <?php elseif ($h['attendance_status'] == 'pending'): ?>
                                                    <span class="badge bg-primary">Programado</span>
                                                <?php elseif ($h['attendance_status'] == 'present'): ?>
                                                    <span class="badge bg-success">Asistió</span>
                                                <?php elseif ($h['attendance_status'] == 'absent'): ?>
                                                    <span class="badge bg-danger">Ausente</span>
                                                <?php endif; ?>

                                                <!-- Cancel Button Logic -->
                                                <?php
                                                $startTime = strtotime($h['start_time']);
                                                $canCancel = ($h['status'] !== 'cancelled' && $h['attendance_status'] === 'pending' && $startTime > (time() + 86400));
                                                if ($canCancel):
                                                ?>
                                                    <form method="POST" class="d-inline ms-2" onsubmit="return confirm('¿Seguro que querés cancelar este turno?');">
                                                        <input type="hidden" name="action" value="cancel_appointment">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $h['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0" style="font-size: 0.75rem;">Cancelar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted p-4">No hay turnos registrados aún.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>
