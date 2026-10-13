<?php
/**
 * Helper Auth: Autentikasi dan Otorisasi Berbasis Peran (Admin & Staff)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Dapatkan data user yang sedang login
 */
function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role'     => $_SESSION['role'] ?? 'staff',
    ];
}

/**
 * Pastikan user sudah login, jika belum redirect ke login.php
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Silakan login terlebih dahulu untuk mengakses halaman ini.';
        header("Location: /login.php");
        exit;
    }
}

/**
 * Pastikan user memiliki role Admin. Staff dilarang keras.
 */
function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        $_SESSION['flash_error'] = 'Akses ditolak: Hanya Administrator yang berhak mengakses halaman ini.';
        header("Location: /dashboard.php");
        exit;
    }
}

/**
 * Dapatkan CSRF Token atau buat baru
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifikasi CSRF Token
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
