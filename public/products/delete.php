<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';

// Otorisasi: Hanya Admin yang boleh menghapus produk
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Metode tidak diizinkan. Penghapusan harus menggunakan metode POST.');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    $_SESSION['flash_error'] = 'Token CSRF tidak valid!';
    header("Location: /products/index.php");
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    $_SESSION['flash_error'] = 'ID produk tidak valid.';
    header("Location: /products/index.php");
    exit;
}

// ATURAN BISNIS SLIDE 33:
// "Produk yang memiliki histori transaksi stok tidak boleh dihapus sembarangan"
$checkHistStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE product_id = :id");
$checkHistStmt->execute(['id' => $id]);
$hasHistory = (int)$checkHistStmt->fetchColumn() > 0;

if ($hasHistory) {
    $_SESSION['flash_error'] = "Aturan Bisnis: Produk tidak dapat dihapus karena sudah memiliki rekam jejak histori transaksi stok. Hapus hanya diperbolehkan untuk produk tanpa transaksi.";
    header("Location: /products/index.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $prod = $stmt->fetch();

    if ($prod) {
        $delStmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $delStmt->execute(['id' => $id]);
        $_SESSION['flash_success'] = "Produk '{$prod['name']}' berhasil dihapus dari sistem.";
    } else {
        $_SESSION['flash_error'] = 'Produk tidak ditemukan.';
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Gagal menghapus produk: ' . $e->getMessage();
}

header("Location: /products/index.php");
exit;
