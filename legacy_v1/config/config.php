<?php
// includes/config.php - VERSIÓN PRODUCCIÓN
// Renombrar este archivo a config.php y reemplazar los valores

// 1. URL DEL SITIO (Sin esto, los links de email fallan)
define('BASE_URL', 'https://itdelivery.com.ar/peiranoB/legacy_v1/');

// 2. BASE DE DATOS
define('DB_HOST', 'localhost'); // Generalmente es localhost en Ferozo
define('DB_NAME', 'c2031975_peiranB'); 
define('DB_USER', 'c2031975_peiranB'); 
define('DB_PASS', 'TU_CONTRASEÑA_ACA');

// 3. RUTAS DE SISTEMA (No tocar salvo que muevas carpetas)
define('ROOT_PATH', dirname(__DIR__) . '/');
define('SRC_PATH', ROOT_PATH . 'src/');
define('TEMPLATES_PATH', ROOT_PATH . 'templates/');

// 4. MICROSOFT GRAPH (Dejar así si no se usa sync real todavía)
define('MS_TENANT_ID', 'common');
define('MS_CLIENT_ID', 'placeholder');
define('MS_CLIENT_SECRET', 'placeholder');
define('MS_CALENDAR_USER', 'placeholder');

// Configuración de Sesión Segura
if (session_status() === PHP_SESSION_NONE) {
    // Forzar cookies seguras si estamos en HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);
    }
    session_start();
}
?>