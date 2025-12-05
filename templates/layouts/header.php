<?php
/**
 * Header Template - White-Label SaaS
 * Dynamic HTML head with brand configuration.
 * 
 * @package WhiteLabel\Templates
 */

require_once __DIR__ . '/../../config/branding.php';

// Page title can be set before including this file
$pageTitle = $pageTitle ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo brand('name'); ?> - <?php echo brand('tagline'); ?>">
    <meta name="theme-color" content="<?php echo brand('primary'); ?>">
    
    <title><?php echo pageTitle($pageTitle); ?></title>
    
    <!-- Favicon (can be customized per brand) -->
    <link rel="icon" type="image/png" href="<?php echo brand('logo_url'); ?>">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?php echo brand('font_url'); ?>" rel="stylesheet">
    
    <!-- Dynamic Branded CSS -->
    <link rel="stylesheet" href="assets/css/style.php">
</head>
<body class="d-flex flex-column min-vh-100">