<?php
// auth.php - Helper Autentikasi Admin iCare NTB

if (session_status() === PHP_SESSION_NONE) {
    // Pada lingkungan serverless Vercel, pastikan path session tersimpan di /tmp
    $tmpDir = sys_get_temp_dir();
    if (!empty($tmpDir) && is_writable($tmpDir)) {
        @session_save_path($tmpDir);
    }
    @session_start();
}

// Kredensial Admin Default (Dapat disesuaikan)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'brmpdiy'); // Password default yang mudah diingat

/**
 * Cek apakah session admin sedang aktif
 */
function is_admin_logged_in(): bool {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Verifikasi login admin
 */
function admin_login(string $username, string $password): bool {
    $user = trim($username);
    $pass = trim($password);

    // Menerima 'admin' / 'brmpdiy' atau 'admin' / 'admin123'
    if ($user === ADMIN_USERNAME && ($pass === ADMIN_PASSWORD || $pass === 'admin123')) {
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_user'] = 'Administrator iCare NTB';
        $_SESSION['admin_login_time'] = time();
        return true;
    }
    return false;
}

/**
 * Logout admin
 */
function admin_logout(): void {
    unset($_SESSION['is_admin']);
    unset($_SESSION['admin_user']);
    unset($_SESSION['admin_login_time']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Wajibkan login untuk mengakses halaman admin
 */
function require_admin_login(): void {
    if (!is_admin_logged_in()) {
        header('Location: login.php?msg=required');
        exit;
    }
}

