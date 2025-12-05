<?php
// src/Utils.php

class Utils {
    
    public static function redirect($url) {
        if (!headers_sent()) {
            header("Location: $url");
            exit;
        }
        echo "<script>window.location.href='$url';</script>";
        exit;
    }

    public static function generateCsrfToken() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken($token) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['csrf_token']) || !isset($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>
