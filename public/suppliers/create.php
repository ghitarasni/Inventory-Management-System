<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';

// Otorisasi: Staff tidak boleh menambah supplier
requireAdmin();

$errors = [];
$name = '';
$contact_info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Akses ditolak: CSRF token tidak valid.');
    }

    $name = trim($_POST['name'] ?? '');
    $contact_info = trim($_POST['contact_info'] ?? '');

    if ($name === '') {
        $errors[] = 'Nama supplier wajib diisi.';
    } elseif (mb_strlen($name) < 3) {
        $errors[] = 'Nama supplier minimal 3 karakter.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_info, created_at, updated_at) VALUES (:name, :contact, NOW(), NOW())");
            $stmt->execute(['name' => $name, 'contact' => $contact_info]);

            $_SESSION['flash_success'] = "Supplier '{$name}' berhasil ditambahkan.";
            header("Location: /suppliers/index.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan supplier: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Tambah Supplier Baru";
require_once __DIR__ . '/../../views/header.php';
?>

<div class="form-shell">
    <div class="form-shell-header">
        <h2>Tambah Supplier Baru</h2>
        <p>Hak Akses Administrator: Daftarkan distributor atau vendor inventaris resmi</p>
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

    <form action="/suppliers/create.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

        <div class="form-group">
            <label class="form-label" for="name">Nama Distributor / Perusahaan *</label>
            <input type="text" id="name" name="name" class="form-input" placeholder="Contoh: PT Pratama Footwear Sentosa" value="<?= e($name) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="contact_info">Kontak & Alamat Email</label>
            <input type="text" id="contact_info" name="contact_info" class="form-input" placeholder="Contoh: 0812-3456-7890 | distribution@pratama.co.id" value="<?= e($contact_info) ?>">
        </div>

        <div class="form-footer-actions">
            <a href="/suppliers/index.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Supplier</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
