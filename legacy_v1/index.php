<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Utils.php';

// Si ya está logueado, ir al dashboard
if (Auth::check()) {
    Utils::redirect('dashboard.php');
}

$error = '';

// Manejo del Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Utils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad (CSRF). Por favor recargue la página.";
    } else {
        $cuit = $_POST['cuit'] ?? '';
        $password = $_POST['password'] ?? '';

        $auth = new Auth();
        $result = $auth->login($cuit, $password);

        if ($result === true) {
            Utils::redirect('dashboard.php');
        } else {
            $error = $result;
        }
    }
}

// Renderizar Vista
$pageTitle = 'Login - Peirano Logística';
// Pasamos variables a la vista
$csrf_token = Utils::generateCsrfToken();

// Renderizamos el layout que a su vez incluirá el contenido si lo estructuramos así,
// o simplemente incluimos header, vista y footer.
// Dado que templates/views/login.php ya incluye header y footer, solo lo incluimos a él.
// Pero templates/views/login.php tiene includes relativos que podrían fallar si no se ajustan.
// Vamos a reescribir la vista de login para que sea limpia o ajustarla.
// Por ahora, usaremos una versión limpia inline para evitar problemas de rutas en views anidadas
// O mejor, corregimos la vista en el siguiente paso. Aquí solo la incluimos.

require_once __DIR__ . '/templates/layouts/header.php';
?>

<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-lg p-4" style="max-width: 400px; width: 100%;">
        <div class="text-center mb-4">
            <i class="bi bi-box-seam-fill text-primary display-1"></i>
            <h3 class="fw-bold mt-3">Peirano Logística</h3>
            <p class="text-muted">Gestión de Turnos</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="mb-3">
                <label for="cuit" class="form-label">CUIT</label>
                <input type="text" class="form-control bg-dark text-light border-secondary" id="cuit" name="cuit"
                    required autofocus>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-control bg-dark text-light border-secondary" id="password"
                    name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Ingresar</button>
        </form>

        <div class="text-center mt-4">
            <a href="register.php" class="text-decoration-none text-muted small hover-text-light">¿No tenés cuenta?
                Registrate acá</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>