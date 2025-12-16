<?php
/**
 * Branding Configuration - White-Label SaaS
 * Centralizes all visual and brand-related settings.
 * 
 * @package WhiteLabel\Config
 */

declare(strict_types=1);

// Ensure config is loaded first
require_once __DIR__ . '/config.php';

// =====================================================
// BRAND CONFIGURATION ARRAY
// =====================================================

$GLOBALS['BRAND'] = [
    // Company Identity
    'name'          => env('COMPANY_NAME', 'Dock Scheduling'),
    'tagline'       => env('COMPANY_TAGLINE', 'Gestión de Turnos'),
    
    // Logo
    'logo_url'      => env('LOGO_URL', 'assets/img/logo.png'),
    'logo_fallback' => env('LOGO_FALLBACK', 'https://via.placeholder.com/150x50?text=LOGO'),
    'logo_height'   => '50', // px
    
    // Colors (CSS format)
    'primary'       => env('PRIMARY_COLOR', '#3B82F6'),
    'primary_dark'  => env('PRIMARY_DARK', '#2563EB'),
    'secondary'     => env('SECONDARY_COLOR', '#1E293B'),
    'accent'        => env('ACCENT_COLOR', '#10B981'),
    
    // Background & Text
    'bg_color'      => '#F8FAFC',
    'text_main'     => '#1E293B',
    'text_muted'    => '#64748B',
    
    // SaaS Provider (your company info)
    'powered_by'    => env('POWERED_BY', 'Daruma Consulting'),
    'powered_url'   => env('POWERED_URL', 'https://darumaconsulting.com'),
    
    // Typography (Google Fonts)
    'font_heading'  => "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
    'font_body'     => "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
    'font_url'      => 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
];

// =====================================================
// HELPER FUNCTIONS
// =====================================================

/**
 * Get brand configuration value
 * 
 * @param string $key Configuration key
 * @return string Value or empty string
 */
function brand(string $key): string
{
    return (string) ($GLOBALS['BRAND'][$key] ?? '');
}

/**
 * Convert HEX color to RGBA
 * 
 * @param string $hex HEX color code
 * @param float $alpha Opacity (0-1)
 * @return string RGBA string
 */
function hexToRgba(string $hex, float $alpha = 1.0): string
{
    $hex = ltrim($hex, '#');
    
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    return "rgba({$r}, {$g}, {$b}, {$alpha})";
}

/**
 * Get page title with brand name
 * 
 * @param string $pageTitle Specific page title
 * @return string Full formatted title
 */
function pageTitle(string $pageTitle = ''): string
{
    $brandName = brand('name');
    $tagline = brand('tagline');
    
    if (empty($pageTitle)) {
        return "{$brandName} | {$tagline}";
    }
    
    return "{$pageTitle} | {$brandName}";
}
