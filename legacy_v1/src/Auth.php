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
            if ($user['role'] !== 'admin' && !$user['email_verified']) return "Email no verificado.";

            // Guardar datos mínimos en sesión
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['company_name'],
                'cuit' => $user['cuit'],
                'role' => $user['role'],
                'branch_id' => $user['branch_id']
            ];
            return true;
        }
        return "CUIT o contraseña incorrectos.";
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header("Location: index.php");
        exit;
    }
}
?>