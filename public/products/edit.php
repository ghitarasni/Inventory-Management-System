<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

// Otorisasi: Hanya Admin yang berhak mengubah master produk
requireAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}

if (!$id) {
    $_SESSION['flash_error'] = 'ID produk tidak valid.';
    header("Location: /products/index.php");
    exit;
}

// Ambil produk
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['flash_error'] = 'Produk tidak ditemukan.';
    header("Location: /products/index.php");
    exit;
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

// Ambil supplier yang sudah terhubung saat ini
$curSuppStmt = $pdo->prepare("SELECT supplier_id FROM product_supplier WHERE product_id = :id");
$curSuppStmt->execute(['id' => $id]);
$currentSuppliers = $curSuppStmt->fetchAll(PDO::FETCH_COLUMN);

$errors = [];
$name = $product['name'];
$sku = $product['sku'];
$category_id = $product['category_id'];
$price = $product['price'];
$stock = $product['stock'];
$selectedSuppliers = $currentSuppliers;
$currentImage = $product['image_path'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Akses ditolak: CSRF token tidak valid.');
    }

    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $price = trim($_POST['price'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    $selectedSuppliers = $_POST['suppliers'] ?? [];

    $errors = validateProduct($_POST, $id, $pdo);

    // Upload Foto Baru jika ada
    $imageFilename = $currentImage;
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

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $updateSql = "UPDATE products 
                          SET sku = :sku, name = :name, category_id = :category_id, 
                              price = :price, stock = :stock, image_path = :image_path, updated_at = NOW() 
                          WHERE id = :id";
            $stmtUpdate = $pdo->prepare($updateSql);
            $stmtUpdate->execute([
                'sku'         => $sku,
                'name'        => $name,
                'category_id' => (int)$category_id,
                'price'       => (float)$price,
                'stock'       => (int)$stock,
                'image_path'  => $imageFilename,
                'id'          => $id
            ]);

            // Sinkronkan relasi Many-to-Many
            $pdo->prepare("DELETE FROM product_supplier WHERE product_id = :id")->execute(['id' => $id]);
            if (!empty($selectedSuppliers)) {
                $psStmt = $pdo->prepare("INSERT INTO product_supplier (product_id, supplier_id) VALUES (:pid, :sid)");
                foreach ($selectedSuppliers as $sid) {
                    $psStmt->execute(['pid' => $id, 'sid' => (int)$sid]);
                }
            }

            $pdo->commit();

            $_SESSION['flash_success'] = "Data produk '{$name}' berhasil diperbarui.";
            header("Location: /products/index.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Gagal memperbarui produk: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Edit Master Sepatu";
require_once __DIR__ . '/../../views/header.php';
?>

<div class="form-shell">
    <div class="form-shell-header">
        <h2>Edit Master Koleksi Sepatu</h2>
        <p>Perbarui rincian spesifikasi, harga, dan supplier distributor</p>
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

    <form action="/products/edit.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

        <div class="form-group">
            <label class="form-label" for="name">Nama Seri Sepatu *</label>
            <input type="text" id="name" name="name" class="form-input" value="<?= e($name) ?>" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group">
                <label class="form-label" for="sku">Kode SKU *</label>
                <input type="text" id="sku" name="sku" class="form-input" value="<?= e($sku) ?>" required>
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
                <input type="number" id="price" name="price" class="form-input" min="0" step="1000" value="<?= e((string)(float)$price) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="stock">Stok Fisik (Pasang) *</label>
                <input type="number" id="stock" name="stock" class="form-input" min="0" value="<?= e((string)(int)$stock) ?>" required>
                <div class="form-help">Disarankan penyesuaian melalui menu Transaksi Stok.</div>
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
            <label class="form-label" for="image">Ganti Foto Produk (Opsional)</label>
            <input type="file" id="image" name="image" class="form-input" accept="image/jpeg,image/png,image/webp">
            <?php if (!empty($currentImage)): ?>
                <div style="margin-top: 10px;">
                    <img src="/uploads/<?= e(basename($currentImage)) ?>" alt="Foto" style="width: 80px; height: 80px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                </div>
            <?php endif; ?>
        </div>

        <div class="form-footer-actions">
            <a href="/products/index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
