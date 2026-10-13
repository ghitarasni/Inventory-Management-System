<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';

requireLogin();

// 1. Statistik Agregat
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalStock = (int)$pdo->query("SELECT COALESCE(SUM(stock), 0) FROM products")->fetchColumn();
$totalSuppliers = (int)$pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$totalValue = (float)$pdo->query("SELECT COALESCE(SUM(price * stock), 0) FROM products")->fetchColumn();

// 2. Produk dengan stok menipis (<= 5)
$lowStockStmt = $pdo->query("SELECT p.*, c.name AS category_name 
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             WHERE p.stock <= 7 
                             ORDER BY p.stock ASC 
                             LIMIT 5");
$lowStockProducts = $lowStockStmt->fetchAll();

// 3. Transaksi stok terbaru (JOIN products, users)
$recentMovesStmt = $pdo->query("SELECT sm.*, p.name AS product_name, p.sku, u.username, u.role 
                                FROM stock_movements sm 
                                JOIN products p ON sm.product_id = p.id 
                                JOIN users u ON sm.user_id = u.id 
                                ORDER BY sm.id DESC 
                                LIMIT 6");
$recentMovements = $recentMovesStmt->fetchAll();

$pageTitle = "Dashboard Inventaris";
require_once __DIR__ . '/../views/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary);">Dashboard Ringkasan Inventaris</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted);">Pantau ketersediaan stok, nilai aset inventaris, dan pergerakan barang</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="/stock/in.php" class="btn btn-secondary btn-sm">+ Barang Masuk</a>
        <a href="/stock/out.php" class="btn btn-secondary btn-sm">- Barang Keluar</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="/products/create.php" class="btn btn-primary btn-sm">+ Tambah Produk</a>
        <?php endif; ?>
    </div>
</div>

<!-- Stat Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Total Seri Sepatu</div>
        <div class="stat-val"><?= number_format($totalProducts) ?></div>
        <div class="stat-sub">Model produk terdaftar</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Unit Fisik</div>
        <div class="stat-val"><?= number_format($totalStock) ?></div>
        <div class="stat-sub">Pasang sepatu di gudang</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Nilai Inventaris</div>
        <div class="stat-val" style="color: #047857;">Rp <?= number_format($totalValue, 0, ',', '.') ?></div>
        <div class="stat-sub">Valuasi aset keseluruhan</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Mitra Supplier</div>
        <div class="stat-val"><?= number_format($totalSuppliers) ?></div>
        <div class="stat-sub">Distributor resmi terhubung</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Transaksi Stok Terkini -->
    <div class="table-card" style="margin-bottom: 0;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1rem; font-weight: 700;">Histori Pergerakan Terkini</h3>
            <a href="/stock/index.php" style="font-size: 0.8rem; color: var(--info); text-decoration: none; font-weight: 600;">Lihat Semua</a>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Tipe</th>
                    <th>Jumlah</th>
                    <th>Operator</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentMovements)): ?>
                    <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">Belum ada histori transaksi.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentMovements as $m): ?>
                        <tr>
                            <td>
                                <strong><?= e($m['product_name']) ?></strong><br>
                                <span style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-muted);"><?= e($m['sku']) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $m['type'] === 'in' ? 'badge-in' : 'badge-out' ?>">
                                    <?= $m['type'] === 'in' ? 'Masuk' : 'Keluar' ?>
                                </span>
                            </td>
                            <td><strong><?= (int)$m['quantity'] ?></strong> pasang</td>
                            <td>
                                <?= e($m['username']) ?>
                                <span style="font-size: 0.7rem; color: var(--text-muted);">(<?= e($m['role']) ?>)</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Peringatan Stok Menipis -->
    <div class="table-card" style="margin-bottom: 0;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--brand-amber);">Peringatan Stok Menipis</h3>
            <a href="/products/index.php" style="font-size: 0.8rem; color: var(--info); text-decoration: none; font-weight: 600;">Katalog Produk</a>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Kategori</th>
                    <th>Sisa Stok</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lowStockProducts)): ?>
                    <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">Seluruh stok produk dalam kondisi aman.</td></tr>
                <?php else: ?>
                    <?php foreach ($lowStockProducts as $lp): ?>
                        <tr>
                            <td>
                                <strong><?= e($lp['name']) ?></strong><br>
                                <span style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-muted);"><?= e($lp['sku']) ?></span>
                            </td>
                            <td><?= e($lp['category_name']) ?></td>
                            <td>
                                <span class="badge <?= (int)$lp['stock'] === 0 ? 'badge-zero' : 'badge-out' ?>">
                                    <?= (int)$lp['stock'] ?> pasang
                                </span>
                            </td>
                            <td>
                                <a href="/stock/in.php?product_id=<?= (int)$lp['id'] ?>" class="btn btn-secondary btn-sm">+ Restock</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
