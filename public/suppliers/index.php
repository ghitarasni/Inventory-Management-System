<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';

requireLogin();

// Ambil supplier beserta jumlah model produk yang disuplai
$sql = "SELECT s.*, COUNT(ps.product_id) AS total_products 
        FROM suppliers s 
        LEFT JOIN product_supplier ps ON s.id = ps.supplier_id 
        GROUP BY s.id 
        ORDER BY s.id ASC";
$suppliers = $pdo->query($sql)->fetchAll();

$pageTitle = "Daftar Supplier";
require_once __DIR__ . '/../../views/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary);">Mitra Distributor & Supplier</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted);">Daftar rekanan resmi penyedia inventaris sepatu branded (Relasi Many-to-Many)</p>
    </div>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <div>
            <a href="/suppliers/create.php" class="btn btn-primary">+ Tambah Supplier Baru</a>
        </div>
    <?php endif; ?>
</div>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Distributor / Supplier</th>
                <th>Kontak & Email</th>
                <th>Model Sepatu Terhubung</th>
                <th>Terdaftar Sejak</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($suppliers)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">
                        Belum ada supplier yang terdaftar.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td>#<?= (int)$s['id'] ?></td>
                        <td>
                            <strong><?= e($s['name']) ?></strong>
                        </td>
                        <td style="color: var(--text-secondary); font-size: 0.85rem;">
                            <?= e($s['contact_info']) ?>
                        </td>
                        <td>
                            <span class="badge" style="background: #eff6ff; color: #1d4ed8; font-weight: 700;">
                                <?= (int)$s['total_products'] ?> model sepatu
                            </span>
                        </td>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">
                            <?= e($s['created_at']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
