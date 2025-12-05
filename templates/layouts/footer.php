<?php
/**
 * Footer Template - White-Label SaaS
 * Dynamic footer with brand copyright and SaaS provider info.
 * 
 * @package WhiteLabel\Templates
 */

require_once __DIR__ . '/../../config/branding.php';
?>
    <!-- Spacer to push footer to bottom -->
    <div class="flex-grow-1"></div>
    
    <footer class="py-4 mt-5">
        <div class="container text-center">
            <div class="row align-items-center">
                <!-- Brand Copyright -->
                <div class="col-md-6 text-md-start mb-2 mb-md-0">
                    <span class="text-muted small">
                        &copy; <?php echo date('Y'); ?> 
                        <strong><?php echo brand('name'); ?></strong>. 
                        Todos los derechos reservados.
                    </span>
                </div>
                
                <!-- Powered By (SaaS Provider) -->
                <div class="col-md-6 text-md-end">
                    <span class="text-muted small">
                        Tecnología por 
                        <a href="<?php echo brand('powered_url'); ?>" 
                           class="text-dark text-decoration-none fw-bold"
                           target="_blank" 
                           rel="noopener noreferrer">
                            <?php echo brand('powered_by'); ?>
                        </a>
                        <i class="bi bi-cpu ms-1"></i>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>