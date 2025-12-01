<?php
// templates/layouts/nav.php
require_once __DIR__ . '/../../src/Auth.php';
$user = Auth::user(); // Obtenemos el usuario logueado
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3 shadow-sm sticky-top">
    <div class="container">
        <!-- LOGO -->
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <!-- Usamos assets/img/logo.png si lo subiste, sino un fallback visual -->
            <img src="assets/img/logo.png" alt="Peirano" height="35" class="d-inline-block align-text-top me-2" 
                 onerror="this.onerror=null; this.src='https://griferiapeirano.com/wp-content/uploads/2023/09/logo-griferia-peirano.png';">
            <span class="text-secondary small fw-bold d-none d-sm-inline" style="letter-spacing: 1px; margin-top: 4px;">LOGÍSTICA</span>
        </a>
        
        <?php if ($user): ?>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center gap-2 mt-3 mt-lg-0">
                    
                    <!-- --- MENÚ SEGÚN ROL --- -->

                    <!-- ROL: OPERARIO (Guardia) -->
                    <?php if ($user['role'] === 'operator'): ?>
                        <li class="nav-item">
                            <a class="nav-link fw-bold text-dark px-3 rounded hover-bg-light" href="index.php?route=operator">
                                <i class="bi bi-truck me-2 text-primary"></i>Control de Acceso
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- ROL: PROVEEDOR (Chofer/Logística) -->
                    <?php if ($user['role'] === 'provider'): ?>
                        <li class="nav-item">
                            <a class="nav-link px-3" href="index.php?route=dashboard">Mis Turnos</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-danger text-white px-4 rounded-pill fw-bold ms-2 shadow-sm" href="index.php?route=reservar">
                                <i class="bi bi-plus-lg me-1"></i> Nuevo Turno
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- ROL: ADMIN (Dios) -->
                    <?php if ($user['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link fw-bold text-danger px-3" href="index.php?route=admin">
                                <i class="bi bi-speedometer2 me-1"></i> Panel Maestro
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link px-3" href="index.php?route=operator">
                                <i class="bi bi-eye me-1"></i> Vista Planta
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- --- INFO USUARIO --- -->
                    <li class="nav-item ms-lg-4 ps-lg-4 border-start border-2">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <div class="text-end me-3 line-height-1 d-none d-lg-block">
                                <small class="text-muted d-block text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.5px;">Usuario</small>
                                <span class="fw-bold text-dark" style="font-size: 0.9rem;"><?php echo substr($user['name'], 0, 15); ?></span>
                            </div>
                            <!-- Versión móvil del nombre -->
                            <span class="fw-bold text-dark d-lg-none"><?php echo $user['name']; ?></span>
                            
                            <a href="index.php?route=logout" class="btn btn-light border btn-sm ms-2" title="Cerrar Sesión" data-bs-toggle="tooltip" data-bs-placement="bottom">
                                <i class="bi bi-box-arrow-right text-danger"></i>
                            </a>
                        </div>
                    </li>

                </ul>
            </div>
        <?php endif; ?>
    </div>
</nav>