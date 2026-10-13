<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';

requireLogin();

// Filter & Pagination
$typeFilter = $_GET['type'] ?? '';
$search = trim($_GET['q'] ?? '');

$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$where = "WHERE 1=1";
$params = [];

if ($typeFilter === 'in' || $typeFilter === 'out') {
    $where .= " AND sm.type = :type";
    $params['type'] = $typeFilter;
}

if ($search !== '') {
    $where .= " AND (p.name LIKE :s_name OR p.sku LIKE :s_sku OR u.username LIKE :s_user)";
    $params['s_name'] = "%{$search}%";
    $params['s_sku']  = "%{$search}%";
    $params['s_user'] = "%{$search}%";
}

// Count total
$countSql = "SELECT COUNT(*) FROM stock_movements sm 
             JOIN products p ON sm.product_id = p.id 
             JOIN users u ON sm.user_id = u.id 
             $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $limit));

// Data query
$dataSql = "SELECT sm.*, p.name AS product_name, p.sku, u.username, u.role 
            FROM stock_movements sm 
            JOIN products p ON sm.product_id = p.id 
            JOIN users u ON sm.user_id = u.id 
            $where 
            ORDER BY sm.id DESC 
            LIMIT $limit OFFSET $offset";
$dataStmt = $pdo->prepare($dataSql);
$dataStmt->execute($params);
$movements = $dataStmt->fetchAll();

$pageTitle = "Histori Transaksi Stok";
require_once __DIR__ . '/../../views/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary);">Histori Transaksi Barang Masuk & Keluar</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted);">Audit log perpindahan stok inventaris dengan pencatatan operator</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="/stock/in.php" class="btn btn-primary btn-sm">+ Transaksi Barang Masuk</a>
        <a href="/stock/out.php" class="btn btn-secondary btn-sm">- Transaksi Barang Keluar</a>
    </div>
</div>

<!-- Toolbar Filter -->
<div class="toolbar-card">
    <form action="/stock/index.php" method="GET" class="search-filter-group">
        <input type="text" name="q" class="form-input" style="max-width: 280px;" placeholder="Cari produk, SKU, atau operator..." value="<?= e($search) ?>">

        <select name="type" class="form-select" style="max-width: 170px;" onchange="this.form.submit()">
            <option value="">Semua Tipe Transaksi</option>
            <option value="in" <?= $typeFilter === 'in' ? 'selected' : '' ?>>Barang Masuk (IN)</option>
            <option value="out" <?= $typeFilter === 'out' ? 'selected' : '' ?>>Barang Keluar (OUT)</option>
        </select>

        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <?php if ($search !== '' || $typeFilter !== ''): ?>
            <a href="/stock/index.php" class="btn btn-secondary btn-sm">Reset</a>
        <?php endif; ?>
    </form>
    <div style="font-size: 0.85rem; color: var(--text-muted);">
        Total: <strong><?= $totalRecords ?></strong> transaksi dicatat
    </div>
</div>

<!-- Table Transaksi -->
<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tanggal & Waktu</th>
                <th>Produk</th>
                <th>Tipe</th>
                <th>Jumlah (Quantity)</th>
                <th>Operator</th>
                <th>Keterangan / Catatan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movements)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        Belum ada data pergerakan stok.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($movements as $m): ?>
                    <tr>
                        <td>#<?= (int)$m['id'] ?></td>
                        <td style="font-size: 0.825rem; color: var(--text-secondary); white-space: nowrap;">
                            <?= e($m['created_at']) ?>
                        </td>
                        <td>
                            <strong><?= e($m['product_name']) ?></strong><br>
                            <code style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-muted);"><?= e($m['sku']) ?></code>
                        </td>
                        <td>
                            <span class="badge <?= $m['type'] === 'in' ? 'badge-in' : 'badge-out' ?>">
                                <?= $m['type'] === 'in' ? 'MASUK' : 'KELUAR' ?>
                            </span>
                        </td>
                        <td style="font-weight: 700;">
                            <?= $m['type'] === 'in' ? '+' : '-' ?><?= (int)$m['quantity'] ?> pasang
                        </td>
                        <td>
                            <?= e($m['username']) ?>
                            <span class="user-badge <?= $m['role'] === 'admin' ? 'role-admin' : 'role-staff' ?>" style="font-size: 0.65rem;">
                                <?= e($m['role']) ?>
                            </span>
                        </td>
                        <td style="font-size: 0.825rem; color: var(--text-secondary);">
                            <?= e($m['notes'] ?: '-') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div>Halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong></div>
            <div class="page-buttons">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php 
                        $query = $_GET;
                        $query['page'] = $i;
                    ?>
                    <a href="?<?= e(http_build_query($query)) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
