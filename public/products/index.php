<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';

requireLogin();

// 1. Parameter Filter, Pencarian, Sorting, & Pagination
$search = trim($_GET['q'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$sort = $_GET['sort'] ?? 'id';
$order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

// Whitelist kolom sorting untuk mencegah SQL Injection (Business Rule)
$allowedSortColumns = [
    'id'    => 'p.id',
    'name'  => 'p.name',
    'price' => 'p.price',
    'stock' => 'p.stock',
    'sku'   => 'p.sku'
];
$sortColumn = $allowedSortColumns[$sort] ?? 'p.id';

// Pagination settings
$limit = 6;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Ambil Kategori untuk dropdown filter
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// Query Base
$where = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (p.name LIKE :search_name OR p.sku LIKE :search_sku)";
    $params['search_name'] = "%{$search}%";
    $params['search_sku'] = "%{$search}%";
}

if ($categoryFilter !== '') {
    $where .= " AND p.category_id = :category_id";
    $params['category_id'] = (int)$categoryFilter;
}

// Total records untuk pagination
$countSql = "SELECT COUNT(*) FROM products p $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProducts / $limit));

// Query Data Produk (JOIN categories & GROUP_CONCAT suppliers)
$dataSql = "SELECT p.*, c.name AS category_name,
                   GROUP_CONCAT(s.name SEPARATOR ', ') AS supplier_names
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_supplier ps ON p.id = ps.product_id
            LEFT JOIN suppliers s ON ps.supplier_id = s.id
            $where
            GROUP BY p.id
            ORDER BY $sortColumn $order
            LIMIT $limit OFFSET $offset";

$dataStmt = $pdo->prepare($dataSql);
$dataStmt->execute($params);
$products = $dataStmt->fetchAll();

$pageTitle = "Daftar Koleksi Sepatu";
require_once __DIR__ . '/../../views/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary);">Katalog Inventaris Sepatu</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted);">Kelola master data produk, relasi distributor, dan harga</p>
    </div>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <div>
            <a href="/products/create.php" class="btn btn-primary">+ Tambah Koleksi Sepatu</a>
        </div>
    <?php endif; ?>
</div>

<!-- Toolbar Search & Filter -->
<div class="toolbar-card">
    <form action="/products/index.php" method="GET" class="search-filter-group">
        <input 
            type="text" 
            name="q" 
            class="form-input" 
            style="max-width: 260px;" 
            placeholder="Cari seri atau SKU..." 
            value="<?= e($search) ?>"
        >
        
        <select name="category" class="form-select" style="max-width: 200px;" onchange="this.form.submit()">
            <option value="">Semua Kategori</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="sort" class="form-select" style="max-width: 170px;" onchange="this.form.submit()">
            <option value="id" <?= $sort === 'id' ? 'selected' : '' ?>>Urut: Terbaru</option>
            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Urut: Nama</option>
            <option value="price" <?= $sort === 'price' ? 'selected' : '' ?>>Urut: Harga</option>
            <option value="stock" <?= $sort === 'stock' ? 'selected' : '' ?>>Urut: Stok</option>
        </select>

        <select name="order" class="form-select" style="max-width: 140px;" onchange="this.form.submit()">
            <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Z-A / Tertinggi</option>
            <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>A-Z / Terendah</option>
        </select>

        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <?php if ($search !== '' || $categoryFilter !== '' || $sort !== 'id'): ?>
            <a href="/products/index.php" class="btn btn-secondary btn-sm">Reset</a>
        <?php endif; ?>
    </form>
    <div style="font-size: 0.85rem; color: var(--text-muted);">
        Total: <strong><?= $totalProducts ?></strong> produk (Hal <?= $page ?> dari <?= $totalPages ?>)
    </div>
</div>

<!-- Table Data Produk -->
<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Foto</th>
                <th>Kode SKU</th>
                <th>Nama Seri Sepatu</th>
                <th>Kategori</th>
                <th>Harga</th>
                <th>Stok Gudang</th>
                <th>Supplier (Distributor)</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        Tidak ada produk ditemukan sesuai filter.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <?php
                        $img = $p['image_path'] ?? '';
                        $imgSrc = (!empty($img) && file_exists(__DIR__ . '/../uploads/' . basename($img))) 
                                  ? '/uploads/' . e(basename($img)) 
                                  : '/uploads/default.jpg';
                    ?>
                    <tr>
                        <td style="width: 60px;">
                            <img src="<?= $imgSrc ?>" alt="<?= e($p['name']) ?>" style="width: 52px; height: 52px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                        </td>
                        <td>
                            <code style="font-family: var(--font-mono); font-size: 0.8rem; background: var(--bg-surface); padding: 3px 6px; border-radius: 4px;"><?= e($p['sku']) ?></code>
                        </td>
                        <td>
                            <strong><?= e($p['name']) ?></strong>
                        </td>
                        <td>
                            <span class="badge" style="background: #f1f5f9; color: var(--text-primary); border: 1px solid var(--border);">
                                <?= e($p['category_name'] ?? 'Umum') ?>
                            </span>
                        </td>
                        <td style="font-weight: 700; color: #047857;">
                            Rp <?= number_format($p['price'], 0, ',', '.') ?>
                        </td>
                        <td>
                            <span class="badge <?= (int)$p['stock'] > 0 ? 'badge-in' : 'badge-zero' ?>">
                                <?= (int)$p['stock'] ?> pasang
                            </span>
                        </td>
                        <td style="font-size: 0.8rem; color: var(--text-secondary); max-width: 180px;">
                            <?= e($p['supplier_names'] ?: 'Belum dihubungkan') ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 6px;">
                                <a href="/stock/in.php?product_id=<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm" title="Transaksi Stok">Stok</a>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <a href="/products/edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    <form action="/products/delete.php" method="POST" onsubmit="return confirm('Hapus produk <?= e(addslashes($p['name'])) ?>?');" style="display: inline;">
                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination Navigation -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div>
                Menampilkan halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong>
            </div>
            <div class="page-buttons">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php 
                        $query = $_GET;
                        $query['page'] = $i;
                        $pageUrl = '?' . http_build_query($query);
                    ?>
                    <a href="<?= e($pageUrl) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
