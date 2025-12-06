<?php
/**
 * Login Page - White-Label SaaS
 * Entry point and authentication for the dock scheduling system.
 * 
 * @package WhiteLabel\Controllers
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/branding.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Utils.php';

// If already logged in, redirect based on role
if (Auth::check()) {
    $role = $_SESSION['user']['role'] ?? 'provider';
    Utils::redirect(in_array($role, ['admin', 'operator']) ? 'admin.php' : 'reservar.php');
}

$error = '';

// Handle Login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Utils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad (CSRF). Por favor recargue la página.";
    } else {
        $cuit = $_POST['cuit'] ?? '';
        $password = $_POST['password'] ?? '';

        $auth = new Auth();
        $result = $auth->login($cuit, $password);

        if ($result === true) {
            $role = $_SESSION['user']['role'] ?? 'provider';
            Utils::redirect(in_array($role, ['admin', 'operator']) ? 'admin.php' : 'reservar.php');
        } else {
            $error = $result;
        }
    }
}

// Generate CSRF Token
$csrf_token = Utils::generateCsrfToken();

// Page Title (dynamic)
$pageTitle = 'Login';
require_once __DIR__ . '/templates/layouts/header.php';
?>

<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-lg p-4" style="max-width: 400px; width: 100%;">
        <div class="text-center mb-4">
            <!-- Dynamic Logo -->
            <img src="<?php echo brand('logo_url'); ?>" 
                 alt="<?php echo brand('name'); ?>" 
                 height="50" 
                 class="mb-3"
                 onerror="this.onerror=null; this.src='<?php echo brand('logo_fallback'); ?>';">
            
            <!-- Dynamic Company Name -->
            <h3 class="fw-bold mt-2"><?php echo brand('name'); ?></h3>
            <p class="text-muted"><?php echo brand('tagline'); ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3">
                <label for="cuit" class="form-label">CUIT</label>
                <input type="text" class="form-control" id="cuit" name="cuit" required autofocus>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Ingresar</button>
        </form>

    </div>
</div>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>