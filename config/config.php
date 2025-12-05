<?php
/**
 * Configuration - White-Label SaaS
 * Loads environment variables and defines global constants.
 * 
 * @package WhiteLabel\Config
 */

declare(strict_types=1);

// =====================================================
// 1. LOAD ENVIRONMENT VARIABLES
// =====================================================

require_once dirname(__DIR__) . '/src/DotEnv.php';

$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    try {
        (new DotEnv($envPath))->load();
    } catch (RuntimeException $e) {
        error_log("Config Error: " . $e->getMessage());
    }
}

// =====================================================
// 2. HELPER FUNCTION
// =====================================================

/**
 * Get environment variable with optional default
 * 
 * @param string $key Variable name
 * @param mixed $default Default value if not set
 * @return string|null
 */
function env(string $key, mixed $default = null): ?string
{
    // Check $_ENV first (our DotEnv sets this)
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    
    // Check $_SERVER as fallback
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    
    // Try getenv for system environment variables
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    
    return $default;
}

// =====================================================
// 3. SYSTEM PATHS
// =====================================================

define('ROOT_PATH', dirname(__DIR__) . '/');
define('SRC_PATH', ROOT_PATH . 'src/');
define('TEMPLATES_PATH', ROOT_PATH . 'templates/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');
define('BASE_URL', env('BASE_URL', '/'));

// =====================================================
// 4. DATABASE CONFIGURATION
// =====================================================

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'dock_scheduling'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// =====================================================
// 5. CALENDAR CONFIGURATION
// =====================================================

// Driver: 'local' (BD only) or 'outlook' (MS Graph)
define('CALENDAR_DRIVER', env('CALENDAR_DRIVER', 'local'));

// Microsoft Graph API (only needed if driver = outlook)
define('MS_TENANT_ID', env('MS_TENANT_ID', 'common'));
define('MS_CLIENT_ID', env('MS_CLIENT_ID', ''));
define('MS_CLIENT_SECRET', env('MS_CLIENT_SECRET', ''));
define('MS_CALENDAR_USER', env('MS_CALENDAR_USER', ''));

// =====================================================
// 6. SECURITY OPTIONS
// =====================================================

define('REQUIRE_EMAIL_VERIFICATION', env('REQUIRE_EMAIL_VERIFICATION', 'true') === 'true');

// =====================================================
// 7. TIMEZONE
// =====================================================

$timezone = env('TIMEZONE', 'America/Argentina/Buenos_Aires');
date_default_timezone_set($timezone);

// =====================================================
// 8. SESSION CONFIGURATION
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    // Secure session settings
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    
    // Only set secure cookie if on HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Strict');
    }
    
    session_start();
}