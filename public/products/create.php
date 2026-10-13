<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

// Otorisasi: Hanya Admin yang boleh menambah master produk
requireAdmin();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

$errors = [];
$name = '';
$sku = '';
$category_id = '';
$price = '';
$stock = '0';
$selectedSuppliers = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifikasi CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Akses ditolak: CSRF token tidak valid.');
    }

    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $price = trim($_POST['price'] ?? '');
    $stock = trim($_POST['stock'] ?? '0');
    $selectedSuppliers = $_POST['suppliers'] ?? [];

    $errors = validateProduct($_POST, null, $pdo);

    // Upload Gambar
    $imageFilename = 'default.jpg';
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $errors[] = 'Format gambar hanya boleh JPG, PNG, atau WEBP.';
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Ukuran berkas gambar maksimal 3MB.';
        } elseif ($file['error'] === UPLOAD_ERR_OK) {
            $imageFilename = 'shoe_' . uniqid() . '.' . $ext;
            move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $imageFilename);
        }
    }

    // Jika validasi sukses, simpan produk dan relasi Many-to-Many supplier dengan DB Transaction
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $insertSql = "INSERT INTO products (sku, name, category_id, price, stock, image_path, created_at, updated_at) 
                          VALUES (:sku, :name, :category_id, :price, :stock, :image_path, NOW(), NOW())";
            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                'sku'         => $sku,
                'name'        => $name,
                'category_id' => (int)$category_id,
                'price'       => (float)$price,
                'stock'       => (int)$stock,
                'image_path'  => $imageFilename
            ]);
            $productId = (int)$pdo->lastInsertId();

            // Simpan relasi many-to-many ke product_supplier
            if (!empty($selectedSuppliers)) {
                $psStmt = $pdo->prepare("INSERT INTO product_supplier (product_id, supplier_id) VALUES (:pid, :sid)");
                foreach ($selectedSuppliers as $sid) {
                    $psStmt->execute(['pid' => $productId, 'sid' => (int)$sid]);
                }
            }

            // Jika stok awal > 0, catat histori initial stock di stock_movements
            if ((int)$stock > 0) {
                $smStmt = $pdo->prepare("INSERT INTO stock_movements (product_id, user_id, type, quantity, notes, created_at, updated_at)
                                         VALUES (:pid, :uid, 'in', :qty, 'Stok awal pembukaan master produk', NOW(), NOW())");
                $smStmt->execute([
                    'pid' => $productId,
                    'uid' => $_SESSION['user_id'],
                    'qty' => (int)$stock
                ]);
            }

            $pdo->commit();

            $_SESSION['flash_success'] = "Master produk '{$name}' berhasil ditambahkan ke inventaris.";
            header("Location: /products/index.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Gagal menyimpan transaksi produk: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Tambah Master Sepatu";
require_once __DIR__ . '/../../views/header.php';
?>

<div class="form-shell">
    <div class="form-shell-header">
        <h2>Tambah Master Koleksi Sepatu</h2>
        <p>Halaman hak akses Administrator: Daftarkan model sepatu dan relasi distributor</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="display: block;">
            <strong>Terjadi kesalahan input:</strong>
            <ul style="margin-left: 20px; margin-top: 6px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="/products/create.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

        <div class="form-group">
            <label class="form-label" for="name">Nama Seri Sepatu *</label>
            <input type="text" id="name" name="name" class="form-input" placeholder="Contoh: Nike Air Jordan 4 Retro White Cement" value="<?= e($name) ?>" required>
            <div class="form-help">Minimal 3 karakter dan nama harus unik.</div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group">
                <label class="form-label" for="sku">Kode SKU *</label>
                <input type="text" id="sku" name="sku" class="form-input" placeholder="Contoh: SHOE-AJ4-001" value="<?= e($sku) ?>" required>
                <div class="form-help">Kode unik inventaris (SKU).</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="category_id">Kategori Sepatu *</label>
                <select id="category_id" name="category_id" class="form-select" required>
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $category_id == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group">
                <label class="form-label" for="price">Harga Satuan (Rp) *</label>
                <input type="number" id="price" name="price" class="form-input" min="0" step="1000" placeholder="Contoh: 3100000" value="<?= e($price) ?>" required>
                <div class="form-help">Harga tidak boleh bernilai negatif.</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="stock">Stok Awal (Pasang) *</label>
                <input type="number" id="stock" name="stock" class="form-input" min="0" placeholder="Contoh: 10" value="<?= e($stock) ?>" required>
                <div class="form-help">Stok fisik ketersediaan barang.</div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Mitra Supplier / Distributor (Relasi Many-to-Many)</label>
            <div style="display: grid; grid-template-columns: 1fr; gap: 8px; background: #f8fafc; padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border);">
                <?php foreach ($suppliers as $s): ?>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; cursor: pointer;">
                        <input type="checkbox" name="suppliers[]" value="<?= (int)$s['id'] ?>" <?= in_array($s['id'], $selectedSuppliers) ? 'checked' : '' ?>>
                        <span><?= e($s['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="image">Foto Produk (Opsional)</label>
            <input type="file" id="image" name="image" class="form-input" accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="form-footer-actions">
            <a href="/products/index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Master Produk</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
