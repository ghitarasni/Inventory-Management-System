<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';

// Jika sudah login, redirect langsung ke dashboard
if (isLoggedIn()) {
    header("Location: /dashboard.php");
    exit;
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID untuk mencegah session fixation
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            $_SESSION['flash_success'] = "Selamat datang kembali, " . $user['username'] . " (" . strtoupper($user['role']) . ")!";
            header("Location: /dashboard.php");
            exit;
        } else {
            $errors[] = 'Username atau password tidak sesuai.';
        }
    }
}

$pageTitle = "Login Inventaris";
require_once __DIR__ . '/../views/header.php';
?>

<div class="form-shell" style="max-width: 440px; margin: 40px auto;">
    <div class="form-shell-header" style="text-align: center;">
        <h2>Login Sistem Inventaris</h2>
        <p>Gunakan akun Admin atau Staff untuk mengakses sistem</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="display: block;">
            <strong>Gagal masuk:</strong>
            <ul style="margin-left: 20px; margin-top: 6px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="/login.php" method="POST">
        <div class="form-group">
            <label class="form-label" for="username">Username</label>
            <input type="text" id="username" name="username" class="form-input" value="<?= e($username) ?>" required autofocus>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" class="form-input" required>
        </div>

        <div style="background: #f8fafc; border: 1px dashed var(--border); padding: 12px; border-radius: var(--radius-md); font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 20px;">
            <strong>Akun Demo Tersedia:</strong><br>
            • Admin: <code>admin</code> / <code>admin123</code> (Full Akses CRUD + Supplier)<br>
            • Staff: <code>staff</code> / <code>staff123</code> (Transaksi Stok & Katalog)
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%;">Masuk ke Sistem</button>
    </form>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
