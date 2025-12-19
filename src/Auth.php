<?php
// src/Auth.php
require_once __DIR__ . '/Database.php';

class Auth {
    // Verificar si hay usuario logueado
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return isset($_SESSION['user']);
    }

    // Obtener usuario actual
    public static function user() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['user'] ?? null;
    }

    // Proteger rutas por Rol
    public static function requireRole($roles) {
        if (!self::check()) {
            header("Location: index.php?route=login&error=auth");
            exit;
        }
        
        $userRole = $_SESSION['user']['role'];
        if (is_string($roles)) $roles = [$roles];

        if (!in_array($userRole, $roles)) {
            // Si no tiene permiso, al dashboard default
            header("Location: index.php?route=dashboard&error=forbidden");
            exit;
        }
    }

    // Lógica de Login
    public function login($cuit, $password) {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE cuit = ?");
        $stmt->execute([$cuit]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Validaciones de negocio
            if ($user['status'] === 'rejected') return "Cuenta bloqueada.";
            
            // Check verification if column exists (backward compatibility or future)
            if ($user['role'] !== 'admin' && isset($user['email_verified']) && !$user['email_verified']) {
                return "Email no verificado.";
            }

            // 2FA CHECK
            if (!empty($user['two_factor_enabled']) && $user['two_factor_enabled'] == 1) {
                // Return special status to trigger 2FA step in controller
                $_SESSION['2fa_pending_user_id'] = $user['id'];
                return "2fa_required";
            }

            // Guardar datos mínimos en sesión (Login Exitoso directo)
            $this->setSession($user);
            return true;
        }
        return "CUIT o contraseña incorrectos.";
    }

    public function verify2FA($code) {
        if (!isset($_SESSION['2fa_pending_user_id'])) return false;
        
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['2fa_pending_user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            require_once __DIR__ . '/Services/TwoFactorService.php';
            $tfa = new TwoFactorService();
            if ($tfa->verifyCode($user['two_factor_secret'], $code)) {
                // Success
                $this->setSession($user);
                unset($_SESSION['2fa_pending_user_id']);
                return true;
            }
        }
        return false;
    }

    private function setSession($user) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['company_name'],
            'cuit' => $user['cuit'],
            'email' => $user['email'] ?? null,
            'role' => $user['role'],
            'branch_id' => $user['branch_id'],
            'default_duration' => $user['default_duration'] ?? null
        ];
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header("Location: index.php");
        exit;
    }
}
?>