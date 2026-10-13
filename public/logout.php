<?php
require_once __DIR__ . '/../helpers/auth.php';

session_start();
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

session_start();
$_SESSION['flash_success'] = 'Anda telah berhasil keluar dari sistem.';
header("Location: /login.php");
exit;
