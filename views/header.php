<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';

$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>BrandKicks Inventory</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="navbar">
        <div class="navbar-inner">
            <a href="/dashboard.php" class="nav-brand">
                <span class="nav-logo-badge">BK</span>
                <span class="nav-title">BrandKicks Inventory</span>
            </a>

            <?php if ($user): ?>
                <nav class="nav-links">
                    <a href="/dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
                    <a href="/products/index.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/products/') !== false ? 'active' : '' ?>">Produk</a>
                    <a href="/stock/index.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/stock/') !== false ? 'active' : '' ?>">Transaksi Stok</a>
                    <a href="/suppliers/index.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/suppliers/') !== false ? 'active' : '' ?>">Supplier</a>
                    
                    <span class="user-badge <?= $user['role'] === 'admin' ? 'role-admin' : 'role-staff' ?>">
                        <?= e(strtoupper($user['role'])) ?>: <?= e($user['username']) ?>
                    </span>
                    
                    <a href="/logout.php" class="nav-item" style="color: #f87171;" onclick="return confirm('Keluar dari sesi?');">Logout</a>
                </nav>
            <?php endif; ?>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success">
                    <span><?= e($_SESSION['flash_success']) ?></span>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger">
                    <span><?= e($_SESSION['flash_error']) ?></span>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>
