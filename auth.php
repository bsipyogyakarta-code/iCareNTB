<?php
// auth.php - Helper Autentikasi Admin iCare NTB (Stateless & Serverless-Proof)

// Konfigurasi Session & Cookie Token
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // Password resmi iCare NTB (juga menerima brmpdiy)
define('AUTH_COOKIE_NAME', 'icare_admin_session');
define('AUTH_SECRET_KEY', 'icare_ntb_secure_jwt_token_secret_key_2026');
define('AUTH_LIFETIME', 86400 * 30); // 30 hari

if (session_status() === PHP_SESSION_NONE) {
    $tmpDir = sys_get_temp_dir();
    if (!empty($tmpDir) && is_writable($tmpDir)) {
        @session_save_path($tmpDir);
    }
    @ini_set('session.gc_maxlifetime', (string)AUTH_LIFETIME);
    @session_set_cookie_params([
        'lifetime' => AUTH_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    @session_start();
}

/**
 * Buat signed token HMAC-SHA256 untuk cookie admin
 */
function generate_auth_token(string $username): string {
    $payload = [
        'user' => $username,
        'name' => 'Administrator iCare NTB',
        'exp' => time() + AUTH_LIFETIME
    ];
    $data = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $data, AUTH_SECRET_KEY);
    return $data . '.' . $signature;
}

/**
 * Validasi signed token dari cookie
 */
function verify_auth_token(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }
    list($data, $sig) = $parts;
    $expectedSig = hash_hmac('sha256', $data, AUTH_SECRET_KEY);
    if (!hash_equals($expectedSig, $sig)) {
        return null;
    }
    $payload = json_decode(base64_decode($data), true);
    if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
        return null;
    }
    return $payload;
}

/**
 * Cek apakah session admin sedang aktif (mendukung pemulihan otomatis via signed cookie)
 */
function is_admin_logged_in(): bool {
    // 1. Cek session memori PHP
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
        return true;
    }

    // 2. Jika session hilang karena container restart di Vercel, pulihkan dari signed cookie
    if (!empty($_COOKIE[AUTH_COOKIE_NAME])) {
        $payload = verify_auth_token($_COOKIE[AUTH_COOKIE_NAME]);
        if ($payload) {
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_user'] = $payload['name'] ?? 'Administrator iCare NTB';
            $_SESSION['admin_login_time'] = time();
            return true;
        }
    }

    return false;
}

/**
 * Verifikasi login admin dan pasang signed cookie
 */
function admin_login(string $username, string $password): bool {
    $user = trim($username);
    $pass = trim($password);

    if ($user === ADMIN_USERNAME && ($pass === ADMIN_PASSWORD || $pass === 'admin123')) {
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_user'] = 'Administrator iCare NTB';
        $_SESSION['admin_login_time'] = time();

        // Pasang signed cookie tahan banting (30 hari)
        $token = generate_auth_token($user);
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        setcookie(AUTH_COOKIE_NAME, $token, [
            'expires' => time() + AUTH_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'secure' => $isSecure,
            'samesite' => 'Lax'
        ]);

        return true;
    }
    return false;
}

/**
 * Logout admin dan bersihkan session serta signed cookie
 */
function admin_logout(): void {
    unset($_SESSION['is_admin']);
    unset($_SESSION['admin_user']);
    unset($_SESSION['admin_login_time']);

    if (isset($_COOKIE[AUTH_COOKIE_NAME])) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(AUTH_COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => $isSecure,
            'samesite' => 'Lax'
        ]);
        unset($_COOKIE[AUTH_COOKIE_NAME]);
    }

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


