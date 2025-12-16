<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/branding.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Utils.php';

// Verificar sesión y rol
Auth::requireRole(['admin', 'operator']);

$pdo = Database::connect();
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Utils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad (CSRF).";
    } elseif (isset($_POST['action'])) {
        if ($_POST['action'] === 'approve_user' && $_SESSION['user']['role'] === 'admin') {
            $userId = $_POST['user_id'];
            $duration = $_POST['default_duration'];
            $stmt = $pdo->prepare("UPDATE users SET status = 'approved', default_duration = ? WHERE id = ?");
            if ($stmt->execute([$duration, $userId])) {
                $message = "Usuario aprobado correctamente.";
            } else {
                $error = "Error al aprobar usuario.";
            }
        } elseif ($_POST['action'] === 'reject_user' && $_SESSION['user']['role'] === 'admin') {
            $userId = $_POST['user_id'];
            $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
            if ($stmt->execute([$userId])) {
                $message = "Usuario rechazado.";
            } else {
                $error = "Error al rechazar usuario.";
            }
        } elseif ($_POST['action'] === 'create_user' && $_SESSION['user']['role'] === 'admin') {
            $cuit = $_POST['cuit'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $company_name = $_POST['company_name'];
            $role = $_POST['role'];
            $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;

            try {
                $stmt = $pdo->prepare("INSERT INTO users (cuit, password_hash, company_name, role, branch_id, status) VALUES (?, ?, ?, ?, ?, 'approved')");
                $stmt->execute([$cuit, $password, $company_name, $role, $branch_id]);
                $message = "Usuario creado exitosamente.";
            } catch (PDOException $e) {
                $error = "Error al crear usuario: " . $e->getMessage();
            }
        }
    }
}

// Fetch Data
$date = $_GET['date'] ?? date('Y-m-d');
// Nota: Auth guarda user data en $_SESSION['user']
$userRole = $_SESSION['user']['role'];
$userBranch = $_SESSION['user']['branch_id'];

$branchFilter = $_GET['branch_id'] ?? ($userRole === 'operator' ? $userBranch : '');

// Pending Users (for Authorizations)
$pendingUsers = [];
if ($userRole === 'admin') {
    $pendingUsers = $pdo->query("SELECT * FROM users WHERE status = 'pending'")->fetchAll();
}

// Branches
$branches = [];
try {
    $branches = $pdo->query("SELECT * FROM branches")->fetchAll();
} catch (PDOException $e) {
    $error = "Error crítico: Falta tabla 'branches'. Ejecute el script de actualización.";
}

// Appointments
$sql = "SELECT a.*, u.company_name, u.cuit, b.name as branch_name 
        FROM appointments a 
        JOIN users u ON a.user_id = u.id 
        LEFT JOIN branches b ON a.branch_id = b.id
        WHERE DATE(a.start_time) = ?";

$params = [$date];

if ($branchFilter) {
    $sql .= " AND a.branch_id = ?";
    $params[] = $branchFilter;
}

$sql .= " ORDER BY a.start_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'Panel de Control';
require_once __DIR__ . '/templates/layouts/header.php';
require_once __DIR__ . '/templates/layouts/nav.php';
?>

<div class="container">
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

    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="turnos-tab" data-bs-toggle="tab" data-bs-target="#turnos" type="button">Turnos</button>
        </li>
        <?php if ($userRole === 'admin'): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="auth-tab" data-bs-toggle="tab" data-bs-target="#auth" type="button">
                    Autorizaciones
                    <?php if (count($pendingUsers) > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-1"><?php echo count($pendingUsers); ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button">Usuarios</button>
            </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content" id="myTabContent">
        <!-- TURNOS TAB -->
        <div class="tab-pane fade show active" id="turnos" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold">Agenda de Turnos</h4>
                <div class="d-flex gap-2">
                    <?php if ($userRole === 'admin'): ?>
                        <a href="setup_integrations.php" class="btn btn-outline-info" title="Configurar Integraciones">
                            <i class="bi bi-gear-fill"></i> <span class="d-none d-md-inline">Configurar</span>
                        </a>
                    <?php endif; ?>
                </div>
                <form class="d-flex gap-2">
                    <?php if ($userRole === 'admin'): ?>
                        <select name="branch_id" class="form-select bg-dark text-white border-secondary" style="width: auto;">
                            <option value="">Todas las Sucursales</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['id']; ?>" <?php echo $branchFilter == $branch['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <input type="date" name="date" class="form-control bg-dark text-white border-secondary" value="<?php echo $date; ?>">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </form>
            </div>

            <div class="card shadow-lg">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th class="py-3 ps-3">Hora</th>
                                    <th class="py-3">Sucursal</th>
                                    <th class="py-3">Empresa</th>
                                    <th class="py-3">Vehículo</th>
                                    <th class="py-3">Carga</th>
                                    <th class="py-3">Chofer/Peón</th>
                                    <th class="py-3 text-center">Recursos</th>
                                    <th class="py-3">Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($appointments) > 0): ?>
                                    <?php foreach ($appointments as $appt): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-info"><?php echo date('H:i', strtotime($appt['start_time'])); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($appt['branch_name'] ?? '-'); ?></span></td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($appt['company_name']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($appt['cuit']); ?></div>
                                            </td>
                                            <td><?php echo $appt['vehicle_type']; ?></td>
                                            <td><?php echo $appt['quantity']; ?> bultos</td>
                                            <td>
                                                <div class="small">
                                                    <i class="bi bi-person-fill"></i> <?php echo htmlspecialchars($appt['driver_name'] ?? '-'); ?>
                                                    <span class="text-muted">(<?php echo htmlspecialchars($appt['driver_dni'] ?? '-'); ?>)</span>
                                                </div>
                                                <?php if (!empty($appt['helper_name'])): ?>
                                                    <div class="small text-info">
                                                        <i class="bi bi-people-fill"></i> <?php echo htmlspecialchars($appt['helper_name']); ?>
                                                        <span class="text-muted">(<?php echo htmlspecialchars($appt['helper_dni']); ?>)</span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($appt['needs_forklift']): ?>
                                                    <span class="badge bg-danger text-white border border-light" title="Autoelevador"><i class="bi bi-exclamation-triangle-fill me-1"></i>AE</span>
                                                <?php endif; ?>
                                                <?php if ($appt['needs_helper']): ?>
                                                    <span class="badge bg-info text-dark" title="Peón">PE</span>
                                                <?php endif; ?>
                                                <?php if (!$appt['needs_forklift'] && !$appt['needs_helper']): ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted fst-italic"><?php echo htmlspecialchars($appt['observations'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center py-5 text-muted">No hay turnos para esta fecha/sucursal.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($userRole === 'admin'): ?>
            <!-- AUTORIZACIONES TAB -->
            <div class="tab-pane fade" id="auth" role="tabpanel">
                <h4 class="fw-bold mb-3">Autorizaciones Pendientes</h4>
                <div class="card shadow-lg">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Empresa</th>
                                        <th>CUIT</th>
                                        <th>Teléfono</th>
                                        <th class="text-end pe-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($pendingUsers) > 0): ?>
                                        <?php foreach ($pendingUsers as $user): ?>
                                            <tr>
                                                <td class="ps-3 fw-bold"><?php echo htmlspecialchars($user['company_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['cuit']); ?></td>
                                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                                <td class="text-end pe-3">
                                                    <button class="btn btn-sm btn-success me-2" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $user['id']; ?>">Aprobar</button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Rechazar usuario?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
                                                        <input type="hidden" name="action" value="reject_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">Rechazar</button>
                                                    </form>

                                                    <!-- Modal Aprobar -->
                                                    <div class="modal fade" id="approveModal<?php echo $user['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content bg-dark text-white">
                                                                <div class="modal-header border-secondary">
                                                                    <h5 class="modal-title">Aprobar <?php echo htmlspecialchars($user['company_name']); ?></h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST">
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
                                                                        <input type="hidden" name="action" value="approve_user">
                                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Duración Predeterminada (minutos)</label>
                                                                            <input type="number" name="default_duration" class="form-control bg-secondary text-white border-secondary" value="60" required>
                                                                            <div class="form-text text-light">Si es < 15 min, podrá reservar sin restricciones de horario.</div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer border-secondary">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                        <button type="submit" class="btn btn-success">Confirmar Aprobación</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center py-5 text-muted">No hay usuarios pendientes.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- USUARIOS TAB -->
            <div class="tab-pane fade" id="users" role="tabpanel">
                <h4 class="fw-bold mb-3">Gestión de Usuarios Internos</h4>
                <div class="card shadow-lg p-4" style="max-width: 600px;">
                    <h5 class="mb-3">Crear Nuevo Usuario</h5>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="create_user">
                        <div class="mb-3">
                            <label class="form-label">Nombre / Razón Social</label>
                            <input type="text" name="company_name" class="form-control bg-secondary text-white border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Usuario (CUIT/DNI)</label>
                            <input type="text" name="cuit" class="form-control bg-secondary text-white border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="password" class="form-control bg-secondary text-white border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rol</label>
                            <select name="role" class="form-select bg-secondary text-white border-secondary" id="roleSelect" required>
                                <option value="operator">Operario (Jefe de Planta/Seguridad)</option>
                                <option value="admin">Administrador (Dueño/Gerencia)</option>
                            </select>
                        </div>
                        <div class="mb-3" id="branchDiv">
                            <label class="form-label">Sucursal Asignada</label>
                            <select name="branch_id" class="form-select bg-secondary text-white border-secondary">
                                <option value="">Seleccionar Sucursal...</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-light">Requerido para Operarios.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Crear Usuario</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Toggle branch select based on role
    const roleSelect = document.getElementById('roleSelect');
    const branchDiv = document.getElementById('branchDiv');
    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            if (this.value === 'admin') {
                branchDiv.style.display = 'none';
            } else {
                branchDiv.style.display = 'block';
            }
        });
    }
</script>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>