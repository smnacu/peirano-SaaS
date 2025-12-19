<?php
/**
 * Reservar Turno - White-Label SaaS
 * Appointment booking wizard with dynamic branding and calendar abstraction.
 * 
 * @package WhiteLabel\Controllers
 */

declare(strict_types=1);

// =====================================================
// DEPENDENCIES
// =====================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/branding.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Utils.php';
require_once __DIR__ . '/src/Services/ReservationService.php';

use Services\ReservationService;

// =====================================================
// AUTHENTICATION
// =====================================================

Auth::requireRole(['client', 'admin', 'operator', 'provider']);

// =====================================================
// INITIALIZATION
// =====================================================

$pdo = Database::connect();
$error = '';
$success = '';

// PRG Pattern: Check for success message in session
if (isset($_GET['status']) && $_GET['status'] === 'success' && isset($_SESSION['reservation_success'])) {
    $success = $_SESSION['reservation_success'];
    unset($_SESSION['reservation_success']);
}

// =====================================================
// LOAD DEFAULTS FROM LAST APPOINTMENT
// =====================================================

$defaults = [
    'vehicle' => '',
    'quantity' => '',
    'forklift' => 0,
    'helper' => 0,
    'driver_name' => '',
    'driver_dni' => '',
    'helper_name' => '',
    'helper_dni' => ''
];

try {
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user']['id']]);
    $lastAppointment = $stmt->fetch();

    if ($lastAppointment) {
        $defaults = [
            'vehicle' => $lastAppointment['vehicle_type'] ?? '',
            'quantity' => $lastAppointment['quantity'] ?? '',
            'forklift' => (int) ($lastAppointment['needs_forklift'] ?? 0),
            'helper' => (int) ($lastAppointment['needs_helper'] ?? 0),
            'driver_name' => $lastAppointment['driver_name'] ?? '',
            'driver_dni' => $lastAppointment['driver_dni'] ?? '',
            'helper_name' => $lastAppointment['helper_name'] ?? '',
            'helper_dni' => $lastAppointment['helper_dni'] ?? ''
        ];
    }
} catch (Exception $e) {
    // Silently ignore defaults error - not critical
    error_log("Reservar defaults error: " . $e->getMessage());
}

// =====================================================
// LOAD BRANCHES & VEHICLES
// =====================================================

$branches = [];
$vehicleTypes = [];
try {
    $branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
    // Try fetch vehicles, fallback to defaults if table missing (during update transition)
    try {
        $vehicleTypes = $pdo->query("SELECT * FROM vehicle_types WHERE active = 1 ORDER BY name")->fetchAll();
    } catch (PDOException $e) {
        // Table might not exist yet if script didn't run. Fallback array.
    }
} catch (PDOException $e) {
    $error = "Error crítico: La base de datos no está actualizada (Falta tabla 'branches'). Por favor contacte al administrador.";
}

// Fallback vehicles if empty
if (empty($vehicleTypes)) {
    $vehicleTypes = [
        ['name' => 'Utilitario / Camioneta', 'block_minutes' => 30],
        ['name' => 'Chasis', 'block_minutes' => 60],
        ['name' => 'Balancín', 'block_minutes' => 60],
        ['name' => 'Semi / Acoplado', 'block_minutes' => 60]
    ];
}

// =====================================================
// FORM PROCESSING
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!Utils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad (CSRF). Por favor recargue la página.";
    } else {
        try {
            $reservationService = new ReservationService();
            
            // Prepare data for service
            $data = [
                'branch_id' => $_POST['branch_id'] ?? 0,
                'date' => $_POST['date'] ?? '',
                'time' => $_POST['time'] ?? '',
                'vehicle_type' => $_POST['vehicle_type'] ?? '',
                'quantity' => (int) ($_POST['quantity'] ?? 0),
                'needs_forklift' => isset($_POST['needs_forklift']),
                'needs_helper' => isset($_POST['needs_helper']),
                'observations' => trim($_POST['observations'] ?? ''),
                'driver_name' => trim($_POST['driver_name'] ?? ''),
                'driver_dni' => trim($_POST['driver_dni'] ?? ''),
                'helper_name' => trim($_POST['helper_name'] ?? ''),
                'helper_dni' => trim($_POST['helper_dni'] ?? '')
            ];

            // Create Reservation
            $reservationService->createReservation($data, $_SESSION['user']);
            
            // Get branch name for success message
            $branchName = 'Sucursal';
            foreach ($branches as $b) {
                if ((int) $b['id'] === (int)$data['branch_id']) {
                    $branchName = $b['name'];
                    break;
                }
            }

            // Success Redirect
            $formattedDate = date('d/m', strtotime($data['date']));
            $_SESSION['reservation_success'] = "¡Listo! Te esperamos el {$formattedDate} a las {$data['time']}hs en {$branchName}.";

            header("Location: reservar.php?status=success");
            exit;

        } catch (Exception $e) {
            $error = $e->getMessage();
            // Add more specific error handling if needed
            if (str_contains($e->getMessage(), "doesn't exist")) {
                 $error .= " (Posiblemente falte actualizar la base de datos).";
            }
        }
    }
}

// =====================================================
// RENDER VIEW
// =====================================================

$pageTitle = 'Reservar Turno';
require_once __DIR__ . '/templates/layouts/header.php';
require_once __DIR__ . '/templates/layouts/nav.php';
?>

<div class="container py-4 wizard-container">
    <!-- Step Indicators -->
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
            <!-- SUCCESS STATE -->
            <div class="text-center py-5 fade-in">
                <i class="bi bi-check-circle-fill text-success display-1"></i>
                <h2 class="mt-3 fw-bold">¡Turno Confirmado!</h2>
                <p class="text-muted lead"><?php echo htmlspecialchars($success); ?></p>
                <a href="reservar.php" class="btn btn-primary btn-lg mt-4 px-5 rounded-pill">
                    Ver Mis Turnos
                </a>
            </div>
        <?php else: ?>
            <!-- BOOKING FORM -->
            <form method="POST" id="bookingForm" onsubmit="showLoader()">
                <input type="hidden" name="csrf_token" value="<?php echo Utils::generateCsrfToken(); ?>">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <!-- =====================================================
                     STEP 1: CARGO DETAILS
                     ===================================================== -->
                <div class="step-content active" id="step-1">
                    <h4 class="fw-bold mb-1">Detalles de Carga</h4>
                    <p class="text-muted mb-4">Seleccioná Sucursal y Carga</p>

                    <!-- Branch Selection -->
                    <label class="form-label fw-bold text-uppercase small text-muted mb-2">Sucursal</label>
                    <div class="row g-3 mb-4">
                        <?php foreach ($branches as $branch): ?>
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="branch_id" 
                                       id="br_<?php echo $branch['id']; ?>" 
                                       value="<?php echo $branch['id']; ?>" required>
                                <label class="card p-3 d-flex justify-content-between align-items-center cursor-pointer h-100" 
                                       for="br_<?php echo $branch['id']; ?>">
                                    <span class="fw-medium"><?php echo htmlspecialchars($branch['name']); ?></span>
                                    <i class="bi bi-geo-alt text-primary fs-5"></i>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Vehicle Selection -->
                    <label class="form-label fw-bold text-uppercase small text-muted mb-2">Vehículo</label>
                    <div class="row g-3 mb-4">
                        <?php 
                        foreach ($vehicleTypes as $vt): 
                            $val = $vt['name']; // Using name as value for now to match DB schema history
                            $label = $vt['name'];
                            $duration = $vt['block_minutes'];
                        ?>
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="vehicle_type" 
                                       id="v_<?php echo htmlspecialchars($val); ?>" 
                                       value="<?php echo htmlspecialchars($val); ?>" required
                                       <?php echo ($defaults['vehicle'] === $val) ? 'checked' : ''; ?>>
                                <label class="card p-3 d-flex justify-content-between align-items-center cursor-pointer h-100" 
                                       for="v_<?php echo htmlspecialchars($val); ?>">
                                    <div>
                                        <span class="fw-medium d-block"><?php echo htmlspecialchars($label); ?></span>
                                        <small class="text-muted"><?php echo $duration; ?> min</small>
                                    </div>
                                    <i class="bi bi-truck text-primary fs-5"></i>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Logistics -->
                    <label class="form-label fw-bold text-uppercase small text-muted mb-2">Logística</label>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="qty" name="quantity" 
                                       required min="1" value="<?php echo $defaults['quantity']; ?>" placeholder="Cantidad">
                                <label for="qty">Cantidad de Bultos / Pallets</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="card p-3 d-flex justify-content-between align-items-center cursor-pointer h-100" for="forklift">
                                <span class="fw-medium">Necesito Autoelevador</span>
                                <div class="form-check form-switch">
                                    <input class="form-check-input fs-5" type="checkbox" name="needs_forklift" 
                                           id="forklift" <?php echo $defaults['forklift'] ? 'checked' : ''; ?>>
                                </div>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="card p-3 d-flex justify-content-between align-items-center cursor-pointer h-100" for="helper">
                                <span class="fw-medium">Necesito Peón</span>
                                <div class="form-check form-switch">
                                    <input class="form-check-input fs-5" type="checkbox" name="needs_helper" 
                                           id="helper" <?php echo $defaults['helper'] ? 'checked' : ''; ?> 
                                           onchange="toggleHelperFields()">
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Driver & Helper Fields -->
                    <div class="row g-3 mb-4">
                        <div class="col-12"><hr class="border-secondary"></div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold text-uppercase small text-muted mb-0">Datos del Chofer</label>
                                <?php if ($defaults['driver_name']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="loadLastDriver()">
                                        <i class="bi bi-magic me-1"></i> Usar último
                                    </button>
                                <?php endif; ?>
                            </div>
                            <input type="text" class="form-control mb-2" name="driver_name" 
                                   placeholder="Nombre y Apellido" required>
                            <input type="text" class="form-control" name="driver_dni" 
                                   placeholder="DNI" required>
                        </div>
                        <div class="col-md-6" id="helper-fields" style="display: none;">
                            <label class="form-label fw-bold text-uppercase small text-muted">Datos del Peón</label>
                            <input type="text" class="form-control mb-2" name="helper_name" placeholder="Nombre y Apellido">
                            <input type="text" class="form-control" name="helper_dni" placeholder="DNI">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-5">
                        <button type="button" class="btn btn-primary px-4 py-2 fw-bold" onclick="nextStep(2)">
                            Siguiente <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- =====================================================
                     STEP 2: DATE & TIME
                     ===================================================== -->
                <div class="step-content" id="step-2">
                    <h4 class="fw-bold mb-1">Fecha y Hora</h4>
                    <p class="text-muted mb-4">Seleccioná un hueco disponible.</p>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Fecha</label>
                            <input type="date" class="form-control form-control-lg" id="date" name="date" 
                                   required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Horarios</label>
                            <input type="hidden" name="time" id="time" required>
                            <div id="time-slots" class="row g-2">
                                <div class="text-muted small fst-italic p-2">
                                    <i class="bi bi-arrow-up-circle me-1"></i>
                                    Seleccioná una fecha para ver los horarios.
                                </div>
                            </div>
                            <div id="time-error" class="text-danger small mt-2" style="display:none">
                                <i class="bi bi-x-circle"></i> Seleccioná un horario.
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-5">
                        <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStep(1)">Atrás</button>
                        <button type="button" class="btn btn-primary px-4 fw-bold" onclick="nextStep(3)">
                            Siguiente <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- =====================================================
                     STEP 3: SUMMARY
                     ===================================================== -->
                <div class="step-content" id="step-3">
                    <h4 class="fw-bold mb-3">Resumen del Turno</h4>
                    
                    <div class="card bg-light border mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6 text-muted">🏢 Sucursal:</div>
                                <div class="col-6 fw-bold text-end" id="sum-branch">-</div>
                                <div class="col-6 text-muted">📅 Fecha:</div>
                                <div class="col-6 fw-bold text-end" id="sum-date">-</div>
                                <div class="col-6 text-muted">⏰ Hora:</div>
                                <div class="col-6 fw-bold text-end text-primary" id="sum-time">-</div>
                                <div class="col-12 border-top my-1"></div>
                                <div class="col-6 text-muted">🚛 Vehículo:</div>
                                <div class="col-6 fw-bold text-end" id="sum-veh">-</div>
                                <div class="col-6 text-muted">📦 Bultos:</div>
                                <div class="col-6 fw-bold text-end" id="sum-qty">-</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones (Opcional)</label>
                        <textarea class="form-control" name="observations" rows="2" 
                                  placeholder="Ej: Necesito entrar marcha atrás..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-5">
                        <button type="button" class="btn btn-outline-secondary px-4" onclick="prevStep(2)">Atrás</button>
                        <button type="submit" class="btn btn-success w-50 py-2 fw-bold shadow-lg">
                            CONFIRMAR <i class="bi bi-check-lg ms-2"></i>
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="spinner-border mb-3"></div>
    <h4 class="fw-bold">Confirmando Turno...</h4>
    <p class="text-muted">Aguarde un instante</p>
</div>

<script>
    // Show loader on submit
    function showLoader() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    // Toggle helper fields visibility
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

    // Initialize on load
    document.addEventListener('DOMContentLoaded', toggleHelperFields);

    // Load last driver data
    function loadLastDriver() {
        document.querySelector('input[name="driver_name"]').value = '<?php echo htmlspecialchars($defaults['driver_name']); ?>';
        document.querySelector('input[name="driver_dni"]').value = '<?php echo htmlspecialchars($defaults['driver_dni']); ?>';
        
        <?php if ($defaults['helper_name']): ?>
            document.getElementById('helper').checked = true;
            toggleHelperFields();
            document.querySelector('input[name="helper_name"]').value = '<?php echo htmlspecialchars($defaults['helper_name']); ?>';
            document.querySelector('input[name="helper_dni"]').value = '<?php echo htmlspecialchars($defaults['helper_dni']); ?>';
        <?php endif; ?>
    }

    // Step navigation
    function nextStep(step) {
        // Validation for step 1
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
        
        // Validation for step 2
        if (step === 3) {
            const d = document.getElementById('date').value;
            const t = document.getElementById('time').value;
            if (!d || !t) return alert('⚠️ Tenés que elegir fecha y hora para seguir.');
            
            // Populate summary
            document.getElementById('sum-branch').innerText = document.querySelector('input[name="branch_id"]:checked').nextElementSibling.innerText.trim();
            document.getElementById('sum-date').innerText = d.split('-').reverse().join('/');
            document.getElementById('sum-time').innerText = t + ' hs';
            document.getElementById('sum-veh').innerText = document.querySelector('input[name="vehicle_type"]:checked').nextElementSibling.innerText.trim();
            document.getElementById('sum-qty').innerText = document.getElementById('qty').value;
        }
        
        // Show step
        document.querySelectorAll('.step-content').forEach(e => e.classList.remove('active'));
        document.getElementById('step-' + step).classList.add('active');
        
        // Update dots
        for (let i = 1; i <= 3; i++) {
            const dot = document.getElementById('dot-' + i);
            if (i <= step) dot.classList.add('active');
            else dot.classList.remove('active');
        }
    }

    function prevStep(step) {
        nextStep(step);
    }

    // Load time slots on date change
    document.getElementById('date').addEventListener('change', function() {
        const date = this.value;
        const branchEl = document.querySelector('input[name="branch_id"]:checked');
        
        if (!branchEl) {
            alert('⚠️ Primero seleccioná una sucursal.');
            this.value = '';
            return;
        }
        
        const branch = branchEl.value;
        const container = document.getElementById('time-slots');
        container.innerHTML = '<div class="col-12 text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Buscando huecos...</div>';

        fetch(`api_check_slots.php?date=${date}&branch_id=${branch}`)
            .then(r => r.json())
            .then(slots => {
                container.innerHTML = '';
                if (slots.length === 0 || slots.error) {
                    container.innerHTML = '<div class="col-12 text-danger text-center">Sin disponibilidad para esta fecha.</div>';
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
                        };
                    }
                    div.appendChild(btn);
                    container.appendChild(div);
                });
            })
            .catch(e => {
                console.error(e);
                container.innerHTML = '<div class="col-12 text-danger text-center">Error de conexión.</div>';
            });
    });
</script>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>