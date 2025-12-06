<?php
/**
 * Logout - White-Label SaaS
 * Destroys session and redirects to login.
 * 
 * @package WhiteLabel\Controllers
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Auth.php';

$auth = new Auth();
$auth->logout();

header('Location: index.php');
exit;
