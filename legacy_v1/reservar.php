<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Utils.php';
require_once __DIR__ . '/src/Services/OutlookSync.php';

// Verificar sesión
Auth::requireRole(['client', 'admin', 'operator']); // Asumiendo que 'client' es el rol por defecto

$pdo = Database::connect();
$error = '';
$success = '';

// PRG Pattern: Check for success message in session
if (isset($_GET['status']) && $_GET['status'] === 'success' && isset($_SESSION['reservation_success'])) {
    $success = $_SESSION['reservation_success'];
    // Don't unset immediately if we want to allow refresh to show it again, 
    // but standard PRG implies showing it once. 
    // Let's keep it for this request.
    unset($_SESSION['reservation_success']);
}

// Cargar defaults del último turno para agilizar
$default_vehicle = '';
$default_quantity = '';
$default_forklift = 0;
$default_helper = 0;
// Driver Defaults
$last_driver_name = '';
$last_driver_dni = '';
$last_helper_name = '';
$last_helper_dni = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user']['id']]);
    $last_appointment = $stmt->fetch();

    if ($last_appointment) {
        $default_vehicle = $last_appointment['vehicle_type'];
        $default_quantity = $last_appointment['quantity'];
        $default_forklift = $last_appointment['needs_forklift'];
        $default_helper = $last_appointment['needs_helper'];
        // Driver Defaults
        $last_driver_name = $last_appointment['driver_name'];
        $last_driver_dni = $last_appointment['driver_dni'];
        $last_helper_name = $last_appointment['helper_name'];
        $last_helper_dni = $last_appointment['helper_dni'];
    }
} catch (Exception $e) {
    // Ignore defaults error
}

// Fetch Branches
$branches = [];
try {
    $branches = $pdo->query("SELECT * FROM branches")->fetchAll();
} catch (PDOException $e) {
    $error = "Error crítico: La base de datos no está actualizada (Falta tabla 'branches'). Por favor contacte al administrador.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Utils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad (CSRF). Por favor recargue la página.";
    } else {
        $branch_id = $_POST['branch_id'] ?? '';
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $vehicle_type = $_POST['vehicle_type'] ?? '';
        $quantity = $_POST['quantity'] ?? '';
        $needs_forklift = isset($_POST['needs_forklift']) ? 1 : 0;
        $needs_helper = isset($_POST['needs_helper']) ? 1 : 0;
        $observations = $_POST['observations'] ?? '';

        // New Fields
        $driver_name = $_POST['driver_name'] ?? '';
        $driver_dni = $_POST['driver_dni'] ?? '';
        $helper_name = $_POST['helper_name'] ?? '';
        $helper_dni = $_POST['helper_dni'] ?? '';

        if (empty($branch_id) || empty($date) || empty($time) || empty($vehicle_type) || empty($quantity) || empty($driver_name) || empty($driver_dni)) {
            $error = "⚠️ Faltan datos obligatorios. Por favor revisá que hayas completado todo, especialmente los datos del chofer.";
        } elseif ($needs_helper && (empty($helper_name) || empty($helper_dni))) {
            $error = "⚠️ Marcaste que necesitás peón, pero faltan sus datos. Por favor completalos.";
        } else {
            $start_time = $date . ' ' . $time . ':00';

            // TODO: Obtener default_duration del usuario desde la DB si no está en sesión
            // Por ahora asumimos 60 si no está set
            $user_duration = 60; 
            // Podríamos hacer una query rápida para obtener la duración real si es crítica
            
            if ($user_duration < 15) {
                $block_minutes = $user_duration;
                $real_minutes = $user_duration;
            } else {
                if ($vehicle_type === 'Utilitario') {
                    $block_minutes = 30;
                    $real_minutes = 25;
                } else {
                    $block_minutes = 60;
                    $real_minutes = 55;
                }
                if ($user_duration != 60) {
                    $block_minutes = $user_duration;
                    $real_minutes = $user_duration - 5;
                }
            }

            $check_end_time = date('Y-m-d H:i:s', strtotime($start_time) + ($block_minutes * 60));
            $event_end_time = date('Y-m-d H:i:s', strtotime($start_time) + ($real_minutes * 60));

            $outlook = new OutlookSync();

            $isAvailable = true;
            if ($block_minutes >= 15) {
                try {
                    $isAvailable = $outlook->checkAvailability($start_time, $check_end_time, $branch_id);
                } catch (Exception $e) {
                    $error = "Error al verificar disponibilidad: " . $e->getMessage();
                    $isAvailable = false;
                }
            }

            if ($isAvailable === false && empty($error)) {
                $error = "¡Ups! Ese horario ya no está disponible. Por favor elegí otro.";
            } elseif (empty($error)) {
                try {
                    // Guardar en BD Local
                    $sql = "INSERT INTO appointments (user_id, branch_id, start_time, end_time, vehicle_type, needs_forklift, needs_helper, quantity, observations, driver_name, driver_dni, helper_name, helper_dni) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$_SESSION['user']['id'], $branch_id, $start_time, $event_end_time, $vehicle_type, $needs_forklift, $needs_helper, $quantity, $observations, $driver_name, $driver_dni, $helper_name, $helper_dni]);
                    $appointment_id = $pdo->lastInsertId();

                    // Crear evento
                    $branchName = 'Sucursal';
                    foreach ($branches as $b) {
                        if ($b['id'] == $branch_id)
                            $branchName = $b['name'];
                    }

                    $companyName = $_SESSION['user']['name'];
                    $subject = "Turno ($branchName): " . $companyName;
                    $description = "Prov: {$companyName} | Vehículo: $vehicle_type | Bultos: $quantity | Sucursal: $branchName | Chofer: $driver_name ($driver_dni)";
                    if ($needs_helper) {
                        $description .= " | Peón: $helper_name ($helper_dni)";
                    }

                    $event_id = $outlook->createEvent($subject, $start_time, $event_end_time, $description);

                    if ($event_id) {
                        $update = $pdo->prepare("UPDATE appointments SET outlook_event_id = ? WHERE id = ?");
                        $update->execute([$event_id, $appointment_id]);
                    }

                    // PRG: Guardar mensaje en sesión y redirigir
                    $_SESSION['reservation_success'] = "¡Listo! Te esperamos el " . date('d/m', strtotime($date)) . " a las " . $time . "hs en $branchName.";
                    header("Location: reservar.php?status=success");
                    exit;

                } catch (PDOException $e) {
                    $error = "Error técnico al guardar: " . $e->getMessage();
                    if (strpos($e->getMessage(), "doesn't exist") !== false) {
                        $error .= " (Posiblemente falte actualizar la base de datos).";
                    }
                }
            }
        }
    }
}

$pageTitle = 'Reservar Turno - Peirano Logística';
require_once __DIR__ . '/templates/layouts/header.php';
require_once __DIR__ . '/templates/nav.php';
?>

<div class="container py-4 wizard-container">
    <div class="position-relative mb-5 px-4">
        <div class="position-absolute top-50 start-0 w-100 border-top border-secondary z-0"></div>
        <div class="d-flex justify-content-between position-relative z-1">
            <div class="step-dot active" id="dot-1">1</div>
            <div class="step-dot" id="dot-2">2</div>
            <div class="step-dot" id="dot-3">3</div>
        </div>
    </div>

    <div class="card shadow-lg p-4 p-md-5">
        <?php if ($success): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle-fill text-success display-1"></i>
                    <h2 class="mt-3 fw-bold">¡Turno Confirmado!</h2>
                    <p class="text-muted lead"><?php echo $success; ?></p>
                    <a href="dashboard.php" class="btn btn-primary btn-lg mt-4 px-5 rounded-pill">Ver Mis Turnos</a>
                </div>
        <?php else: ?>
                <form method="POST" id="bookingForm" onsubmit="showLoader()">
                    <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
                    <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center mb-4"><i
                                    class="bi bi-exclamation-triangle-fill me-2"></i>
                                <div><?php echo $error; ?></div>
                            </div>
                    <?php endif; ?>

                    <div class="step-content active" id="step-1">
                        <h4 class="fw-bold mb-1">Detalles de Carga</h4>
                        <p class="text-muted mb-4">Seleccioná Sucursal y Carga</p>

                        <label class="form-label fw-bold text-uppercase small text-muted mb-2">Sucursal</label>
                        <div class="row g-3 mb-4">
                            <?php foreach ($branches as $branch): ?>
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="branch_id" id="br_<?php echo $branch['id'] ?>"
                                            value="<?php echo $branch['id'] ?>" required>
                                        <label class="card p-3 d-flex justify-content-between align-items-center cursor-pointer h-100"
                                            for="br_<?php echo $branch['id'] ?>">
                                            <span class="fw-medium"><?php echo htmlspecialchars($branch['name']) ?></span>
                                            <i class="bi bi-geo-alt text-primary fs-5"></i>
                                        </label>
                                    </div>
                            <?php endforeach; ?>
                        </div>

                        <label class="form-label fw-bold text-uppercase small text-muted mb-2">Vehículo</label>
                        <div class="row g-3 mb-4">
                            <?php $opts = ['Utilitario' => 'Utilitario / Camioneta', 'Chasis' => 'Chasis', 'Balancin' => 'Balancín', 'Semi_Acoplado' => 'Semi / Acoplado'];
                            foreach ($opts as $val => $label): ?>
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="vehicle_type" id="v_<?php echo $val ?>"
                                            value="<?php echo $val ?>" required <?php echo ($default_vehicle == $val) ? 'checked' : '' ?>>
                                        <label class="card p-3 d-flex justify-content-between align-items-center cursor-pointer h-100"
                                            for="v_<?php echo $val ?>">
                                            <span class="fw-medium"><?php echo $label ?></span>
                                            <i class="bi bi-truck text-primary fs-5"></i>
                                        </label>
                                    </div>
                            <?php endforeach; ?>
                        </div>

                        <label class="form-label fw-bold text-uppercase small text-muted mb-2">Logística</label>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <div class="form-floating text-dark">
                                    <input type="number" class="form-control bg-dark text-light border-secondary" id="qty"
                                        name="quantity" required min="1" value="<?php echo $default_quantity ?>"
                                        placeholder="Cantidad">
                                    <label for="qty" class="text-muted">Cantidad de Bultos / Pallets</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="card p-3 d-flex justify-content-between align-items-center cursor-pointer h-100"
                                    for="forklift">
                                    <span class="fw-medium">Necesito Autoelevador</span>
                                    <div class="form-check form-switch"><input class="form-check-input fs-5" type="checkbox"
                                            name="needs_forklift" id="forklift" <?php echo $default_forklift ? 'checked' : '' ?>></div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="card p-3 d-flex justify-content-between align-items-center cursor-pointer h-100"
                                    for="helper">
                                    <span class="fw-medium">Necesito Peón</span>
                                    <div class="form-check form-switch"><input class="form-check-input fs-5" type="checkbox"
                                            name="needs_helper" id="helper" <?php echo $default_helper ? 'checked' : '' ?>
                                            onchange="toggleHelperFields()"></div>
                                </label>
                            </div>
                        </div>

                        <!-- Driver & Helper Fields -->
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <hr class="border-secondary">
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-bold text-uppercase small text-muted mb-0">Datos del Chofer</label>
                                    <?php if ($last_driver_name): ?>
                                            <button type="button" class="btn btn-sm btn-outline-info py-0" onclick="loadLastDriver()">
                                                <i class="bi bi-magic me-1"></i> Usar último
                                            </button>
                                    <?php endif; ?>
                                </div>
                                <input type="text" class="form-control bg-dark text-light border-secondary mb-2"
                                    name="driver_name" placeholder="Nombre y Apellido" required>
                                <input type="text" class="form-control bg-dark text-light border-secondary" name="driver_dni"
                                    placeholder="DNI" required>
                            </div>
                            <div class="col-md-6" id="helper-fields" style="display: none;">
                                <label class="form-label fw-bold text-uppercase small text-muted">Datos del Peón</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary mb-2"
                                    name="helper_name" placeholder="Nombre y Apellido">
                                <input type="text" class="form-control bg-dark text-light border-secondary" name="helper_dni"
                                    placeholder="DNI">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-5">
                            <button type="button" class="btn btn-primary px-4 py-2 fw-bold" onclick="nextStep(2)">Siguiente <i
                                    class="bi bi-arrow-right ms-2"></i></button>
                        </div>
                    </div>

                    <div class="step-content" id="step-2">
                        <h4 class="fw-bold mb-1">Fecha y Hora</h4>
                        <p class="text-muted mb-4">Seleccioná un hueco disponible.</p>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Fecha</label>
                                <input type="date" class="form-control form-control-lg bg-dark text-light border-secondary"
                                    id="date" name="date" required min="<?php echo date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Horarios</label>
                                <input type="hidden" name="time" id="time" required>
                                <div id="time-slots" class="row g-2">
                                    <div class="text-muted small fst-italic p-2"><i class="bi bi-arrow-up-circle me-1"></i>
                                        Seleccioná una fecha para ver los horarios.</div>
                                </div>
                                <div id="time-error" class="text-danger small mt-2" style="display:none"><i
                                        class="bi bi-x-circle"></i> Seleccioná un horario.</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-5">
                            <button type="button" class="btn btn-outline-light px-4" onclick="prevStep(1)">Atrás</button>
                            <button type="button" class="btn btn-primary px-4 fw-bold" onclick="nextStep(3)">Siguiente <i
                                    class="bi bi-arrow-right ms-2"></i></button>
                        </div>
                    </div>

                    <div class="step-content" id="step-3">
                        <h4 class="fw-bold mb-3">Resumen del Turno</h4>
                        <div class="card bg-dark border-secondary mb-4">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-6 text-muted">🏢 Sucursal:</div>
                                    <div class="col-6 fw-bold text-end text-light" id="sum-branch">-</div>
                                    <div class="col-6 text-muted">📅 Fecha:</div>
                                    <div class="col-6 fw-bold text-end text-light" id="sum-date">-</div>
                                    <div class="col-6 text-muted">⏰ Hora:</div>
                                    <div class="col-6 fw-bold text-end text-info" id="sum-time">-</div>
                                    <div class="col-12 border-top border-secondary my-1"></div>
                                    <div class="col-6 text-muted">🚛 Vehículo:</div>
                                    <div class="col-6 fw-bold text-end text-light" id="sum-veh">-</div>
                                    <div class="col-6 text-muted">📦 Bultos:</div>
                                    <div class="col-6 fw-bold text-end text-light" id="sum-qty">-</div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observaciones (Opcional)</label>
                            <textarea class="form-control bg-dark text-light border-secondary" name="observations" rows="2"
                                placeholder="Ej: Necesito entrar marcha atrás..."></textarea>
                        </div>
                        <div class="d-flex justify-content-between mt-5">
                            <button type="button" class="btn btn-outline-light px-4" onclick="prevStep(2)">Atrás</button>
                            <button type="submit" class="btn btn-success w-50 py-2 fw-bold shadow-lg">CONFIRMAR <i
                                    class="bi bi-check-lg ms-2"></i></button>
                        </div>
                    </div>
                </form>
        <?php endif; ?>
    </div>
</div>

<style>
    .wizard-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .step-dot {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--card-bg);
        border: 2px solid #334155;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        transition: all 0.3s;
    }

    .step-dot.active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
    }

    .step-content {
        display: none;
        animation: fadeIn 0.4s ease;
    }

    .step-content.active {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .btn-check:checked+label {
        background-color: rgba(59, 130, 246, 0.15);
        border-color: var(--primary-color);
        box-shadow: 0 0 0 1px var(--primary-color);
    }

    .btn-time {
        width: 100%;
        background: #0f172a;
        border: 1px solid #334155;
        color: #94a3b8;
        margin-bottom: 0.5rem;
        transition: all 0.2s;
    }

    .btn-time:hover:not(:disabled) {
        border-color: var(--primary-color);
        color: white;
    }

    .btn-time.active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    .btn-time:disabled {
        opacity: 0.4;
        text-decoration: line-through;
        cursor: not-allowed;
    }

    .cursor-pointer {
        cursor: pointer;
    }
</style>

<div id="loadingOverlay"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.9); z-index: 9999; backdrop-filter: blur(5px);">
    <div class="position-absolute top-50 left-50 translate-middle text-center" style="left: 50%;">
        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
        <h4 class="fw-bold">Confirmando Turno...</h4>
        <p class="text-muted">Aguarde un instante</p>
    </div>
</div>

<script>
    function showLoader() { document.getElementById('loadingOverlay').style.display = 'block'; }

    function toggleHelperFields() {
        const helperCheck = document.getElementById('helper');
        const helperFields = document.getElementById('helper-fields');
        const helperName = document.querySelector('input[name="helper_name"]');
        const helperDni = document.querySelector('input[name="helper_dni"]');

        if (helperCheck.checked) {
            helperFields.style.display = 'block';
            helperName.required = true;
            helperDni.required = true;
        } else {
            helperFields.style.display = 'none';
            helperName.required = false;
            helperDni.required = false;
        }
    }

    document.addEventListener('DOMContentLoaded', toggleHelperFields);

    function loadLastDriver() {
        document.querySelector('input[name="driver_name"]').value = '<?php echo htmlspecialchars($last_driver_name); ?>';
        document.querySelector('input[name="driver_dni"]').value = '<?php echo htmlspecialchars($last_driver_dni); ?>';
        
        <?php if ($last_helper_name): ?>
                document.getElementById('helper').checked = true;
                toggleHelperFields();
                document.querySelector('input[name="helper_name"]').value = '<?php echo htmlspecialchars($last_helper_name); ?>';
                document.querySelector('input[name="helper_dni"]').value = '<?php echo htmlspecialchars($last_helper_dni); ?>';
        <?php endif; ?>
    }

    function nextStep(step) {
        if (step === 2) {
            const branch = document.querySelector('input[name="branch_id"]:checked');
            const vehicle = document.querySelector('input[name="vehicle_type"]:checked');
            const qty = document.getElementById('qty').value;

            const driverName = document.querySelector('input[name="driver_name"]').value;
            const driverDni = document.querySelector('input[name="driver_dni"]').value;

            const needsHelper = document.getElementById('helper').checked;
            const helperName = document.querySelector('input[name="helper_name"]').value;
            const helperDni = document.querySelector('input[name="helper_dni"]').value;

            if (!branch) return alert('⚠️ Seleccioná una sucursal.');
            if (!vehicle) return alert('⚠️ Seleccioná un tipo de vehículo.');
            if (!qty || qty < 1) return alert('⚠️ Ingresá una cantidad válida de bultos.');

            if (!driverName || !driverDni) return alert('⚠️ Completá los datos del chofer.');
            if (needsHelper && (!helperName || !helperDni)) return alert('⚠️ Completá los datos del peón.');
        }
        if (step === 3) {
            const d = document.getElementById('date').value;
            const t = document.getElementById('time').value;
            if (!d || !t) return alert('⚠️ Tenés que elegir fecha y hora para seguir.');
            document.getElementById('sum-branch').innerText = document.querySelector('input[name="branch_id"]:checked').nextElementSibling.innerText.trim();
            document.getElementById('sum-date').innerText = d.split('-').reverse().join('/');
            document.getElementById('sum-time').innerText = t + ' hs';
            document.getElementById('sum-veh').innerText = document.querySelector('input[name="vehicle_type"]:checked').nextElementSibling.innerText.trim();
            document.getElementById('sum-qty').innerText = document.getElementById('qty').value;
        }
        document.querySelectorAll('.step-content').forEach(e => e.classList.remove('active'));
        document.getElementById('step-' + step).classList.add('active');
        for (let i = 1; i <= 3; i++) {
            const dot = document.getElementById('dot-' + i);
            if (i <= step) dot.classList.add('active');
            else dot.classList.remove('active');
        }
    }
    function prevStep(step) { nextStep(step); }

    document.getElementById('date').addEventListener('change', function () {
        const date = this.value;
        const branch = document.querySelector('input[name="branch_id"]:checked').value;
        const container = document.getElementById('time-slots');
        container.innerHTML = '<div class="col-12 text-center py-3"><div class="spinner-border text-primary spinner-border-sm"></div> Buscando huecos...</div>';

        // Updated URL to point to includes/check_slots.php -> NEEDS FIX
        // We need to create a route for checking slots or point to a new controller
        // For now, let's assume we will create a 'check_slots.php' in the root or handle it via index.php
        // Let's point to 'api_check_slots.php' which we will create next.
        fetch(`api_check_slots.php?date=${date}&branch_id=${branch}`)
            .then(r => r.json())
            .then(slots => {
                container.innerHTML = '';
                if (slots.length === 0 || slots.error) {
                    container.innerHTML = '<div class="col-12 text-danger text-center">Sin disponibilidad.</div>';
                    return;
                }
                slots.forEach(slot => {
                    const div = document.createElement('div');
                    div.className = 'col-3 col-md-2';
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `btn btn-time ${slot.available ? '' : 'disabled'}`;
                    btn.innerText = slot.time;
                    if (!slot.available) {
                        btn.disabled = true;
                    } else {
                        btn.onclick = () => {
                            document.querySelectorAll('.btn-time').forEach(b => b.classList.remove('active'));
                            btn.classList.add('active');
                            document.getElementById('time').value = slot.time;
                            document.getElementById('time-error').style.display = 'none';
                        }
                    }
                    div.appendChild(btn);
                    container.appendChild(div);
                });
            })
            .catch(e => { console.error(e); container.innerHTML = '<div class="col-12 text-danger text-center">Error de conexión.</div>'; });
    });
</script>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>