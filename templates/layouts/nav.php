<?php
/**
 * Navigation Template - White-Label SaaS
 * Dynamic navbar with brand logo and user menu.
 * 
 * @package WhiteLabel\Templates
 */

require_once __DIR__ . '/../../config/branding.php';
require_once __DIR__ . '/../../src/Auth.php';

$user = Auth::user();
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3 shadow-sm sticky-top">
    <div class="container">
        <!-- LOGO & BRAND -->
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="<?php echo brand('logo_url'); ?>" 
                 alt="<?php echo brand('name'); ?>" 
                 height="<?php echo brand('logo_height'); ?>" 
                 class="d-inline-block align-text-top me-2"
                 onerror="this.onerror=null; this.src='<?php echo brand('logo_fallback'); ?>';">
            <span class="text-secondary small fw-bold d-none d-sm-inline text-uppercase" 
                  style="letter-spacing: 1px; margin-top: 4px;">
                <?php echo brand('tagline'); ?>
            </span>
        </a>
        
        <?php if ($user): ?>
            <!-- Mobile Toggle -->
            <button class="navbar-toggler border-0" type="button" 
                    data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center gap-2 mt-3 mt-lg-0">
                    
                    <!-- === MENU BY ROLE === -->
                    
                    <?php if ($user['role'] === 'client' || $user['role'] === 'provider'): ?>
                        <!-- ROLE: PROVIDER/CLIENT -->
                        <li class="nav-item">
                            <a class="nav-link px-3" href="dashboard.php">
                                <i class="bi bi-calendar-check me-1"></i> Mis Turnos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary text-white px-4 rounded-pill fw-bold ms-2 shadow-sm" 
                               href="reservar.php">
                                <i class="bi bi-plus-lg me-1"></i> Nuevo Turno
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user['role'] === 'operator'): ?>
                        <!-- ROLE: OPERATOR (Guard/Plant Manager) -->
                        <li class="nav-item">
                            <a class="nav-link fw-bold px-3" href="admin.php">
                                <i class="bi bi-truck me-2 text-primary"></i>Control de Acceso
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user['role'] === 'admin'): ?>
                        <!-- ROLE: ADMIN (Full Access) -->
                        <li class="nav-item">
                            <a class="nav-link fw-bold text-primary px-3" href="admin.php">
                                <i class="bi bi-speedometer2 me-1"></i> Panel Maestro
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link px-3" href="admin.php?view=operator">
                                <i class="bi bi-eye me-1"></i> Vista Planta
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- === USER INFO === -->
                    <li class="nav-item ms-lg-4 ps-lg-4 border-start border-2">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <!-- Desktop User Info -->
                            <div class="text-end me-3 line-height-1 d-none d-lg-block">
                                <small class="text-muted d-block text-uppercase" 
                                       style="font-size: 0.65rem; letter-spacing: 0.5px;">
                                    Usuario
                                </small>
                                <span class="fw-bold text-dark" style="font-size: 0.9rem;">
                                    <?php echo htmlspecialchars(substr($user['name'], 0, 20)); ?>
                                </span>
                            </div>
                            
                            <!-- Mobile User Name -->
                            <span class="fw-bold text-dark d-lg-none">
                                <?php echo htmlspecialchars($user['name']); ?>
                            </span>
                            
                            <!-- Logout Button -->
                            <a href="logout.php" 
                               class="btn btn-light border btn-sm ms-2" 
                               title="Cerrar Sesión">
                                <i class="bi bi-box-arrow-right text-danger"></i>
                            </a>
                        </div>
                    </li>
                    
                </ul>
            </div>
        <?php endif; ?>
    </div>
</nav>