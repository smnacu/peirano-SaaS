<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/branding.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Utils.php';

if (Auth::check()) {
    $role = $_SESSION['user']['role'] ?? 'provider';
    Utils::redirect(in_array($role, ['admin', 'operator']) ? 'admin.php' : 'reservar.php');
}

$error = '';
$show2FA = false;
$auth = new Auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        if (!Utils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $error = "Token de seguridad inválido.";
        } else {
            $cuit = $_POST['cuit'] ?? '';
            $password = $_POST['password'] ?? '';
            
            $loginResult = $auth->login($cuit, $password);
            
            if ($loginResult === true) {
                Auth::redirectAfterLogin();
            } elseif ($loginResult === '2fa_required') {
                $show2FA = true;
            } else {
                $error = $loginResult;
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'verify_2fa') {
        if (!Utils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $error = "Token de seguridad inválido.";
        } else {
            $code = $_POST['code'] ?? '';
            if ($auth->verify2FA($code)) {
                Auth::redirectAfterLogin();
            } else {
                $error = "Código de verificación incorrecto.";
                $show2FA = true;
            }
        }
    }
}

if (Auth::check() && !isset($_SESSION['2fa_pending_user_id'])) {
    Auth::redirectAfterLogin();
}

$pageTitle = 'Login';
$hideNav = true;
require_once __DIR__ . '/templates/layouts/header.php';
?>

<div class="container d-flex flex-column justify-content-center align-items-center min-vh-100">
    <div class="card shadow-lg border-0" style="width: 100%; max-width: 400px;">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <img src="<?php echo ASSETS_PATH; ?>img/logo.png" alt="Logo" class="img-fluid mb-3" style="max-height: 80px;">
                <h4 class="fw-bold text-dark"><?php echo brand('name'); ?></h4>
                <p class="text-muted small">Sistema de Gestión de Turnos</p>
            </div>

            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success small text-center">Registro exitoso. Espere aprobación.</div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger small text-center"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (isset($show2FA) && $show2FA): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="verify_2fa">
                    <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
                    
                    <div class="mb-3 text-center">
                        <label class="form-label text-muted small fw-bold">CÓDIGO DE VERIFICACIÓN (2FA)</label>
                        <i class="bi bi-shield-lock d-block fs-1 text-primary my-2"></i>
                        <input type="text" name="code" class="form-control text-center fs-4 letter-spacing-2" placeholder="000000" maxlength="6" autofocus required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Verificar</button>
                    <a href="index.php" class="btn btn-link w-100 btn-sm text-muted mt-2">Volver al Login</a>
                </form>

            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">

                    <div class="mb-3">
                        <label for="cuit" class="form-label small fw-bold text-muted">CUIT / USUARIO</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" id="cuit" name="cuit" placeholder="Ingrese CUIT" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label small fw-bold text-muted">CONTRASEÑA</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">INGRESAR</button>
                </form>
                
                <div class="text-center mt-3">
                    <a href="register.php" class="small text-decoration-none">¿No tenés cuenta? Registrate aquí</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mt-4 text-center text-muted small">
        &copy; <?php echo date('Y'); ?> <?php echo brand('name'); ?>
    </div>
</div>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>