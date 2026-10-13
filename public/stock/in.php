<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';

requireLogin();

$products = $pdo->query("SELECT id, sku, name, stock FROM products ORDER BY name ASC")->fetchAll();
$selectedProductId = (int)($_GET['product_id'] ?? 0);

$errors = [];
$quantity = '';
$notes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Akses ditolak: CSRF token tidak valid.');
    }

    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = trim($_POST['quantity'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Validasi
    if ($productId <= 0) {
        $errors[] = 'Pilih produk yang akan ditambahkan stoknya.';
    }

    if ($quantity === '' || !filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        $errors[] = 'Jumlah barang masuk harus berupa bilangan bulat lebih dari 0.';
    }

    if (empty($errors)) {
        try {
            // SLIDE 36: Transaksi menggunakan commit/rollback
            $pdo->beginTransaction();

            // Lock row dengan FOR UPDATE untuk mencegah race condition
            $lockStmt = $pdo->prepare("SELECT id, name, stock FROM products WHERE id = :id FOR UPDATE");
            $lockStmt->execute(['id' => $productId]);
            $prod = $lockStmt->fetch();

            if (!$prod) {
                throw new Exception('Produk tidak ditemukan di database.');
            }

            // Update penambahan stok produk
            $updateStmt = $pdo->prepare("UPDATE products SET stock = stock + :qty, updated_at = NOW() WHERE id = :id");
            $updateStmt->execute(['qty' => (int)$quantity, 'id' => $productId]);

            // Catat histori stock_movements
            $insertMove = $pdo->prepare("INSERT INTO stock_movements (product_id, user_id, type, quantity, notes, created_at, updated_at) 
                                         VALUES (:pid, :uid, 'in', :qty, :notes, NOW(), NOW())");
            $insertMove->execute([
                'pid'   => $productId,
                'uid'   => $_SESSION['user_id'],
                'qty'   => (int)$quantity,
                'notes' => $notes ?: 'Penerimaan stok masuk gudang'
            ]);

            $pdo->commit();

            $_SESSION['flash_success'] = "Berhasil menambahkan " . (int)$quantity . " pasang stok untuk '{$prod['name']}'.";
            header("Location: /stock/index.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Transaksi gagal dibatalkan (Rollback): ' . $e->getMessage();
        }
    }
}

$pageTitle = "Transaksi Barang Masuk";
require_once __DIR__ . '/../../views/header.php';
?>

<div class="form-shell">
    <div class="form-shell-header">
        <h2>Transaksi Barang Masuk (Stok In)</h2>
        <p>Penerimaan unit sepatu dari pabrik atau supplier distributor resmi</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="display: block;">
            <strong>Gagal memproses transaksi:</strong>
            <ul style="margin-left: 20px; margin-top: 6px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="/stock/in.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

        <div class="form-group">
            <label class="form-label" for="product_id">Pilih Produk Sepatu *</label>
            <select id="product_id" name="product_id" class="form-select" required>
                <option value="">-- Pilih Produk --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= ($selectedProductId === (int)$p['id']) ? 'selected' : '' ?>>
                        [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Sisa: <?= (int)$p['stock'] ?> pasang)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="quantity">Jumlah Masuk (Pasang) *</label>
            <input type="number" id="quantity" name="quantity" class="form-input" min="1" placeholder="Contoh: 20" value="<?= e($quantity) ?>" required>
            <div class="form-help">Jumlah harus berupa bilangan bulat positif (> 0).</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="notes">Catatan Transaksi / No. Dokumen Penerimaan</label>
            <textarea id="notes" name="notes" class="form-textarea" rows="3" placeholder="Contoh: Restock batch Oktober dari PT Mitra Sepatu Indonesia"><?= e($notes) ?></textarea>
        </div>

        <div class="form-footer-actions">
            <a href="/stock/index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Transaksi Masuk</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
