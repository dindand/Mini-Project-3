<?php
/**
 * Halaman Login Pengguna
 * Sesuai Slide 22 & 23 (Session, Password Hashing, Prepared Statement)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/flash.php';
require_once __DIR__ . '/../helpers/validation.php';

// Jika sudah login, langsung lempar ke dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF
    if (!validate_csrf()) {
        $error = 'Sesi formulir kadaluarsa. Silakan refresh dan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Username dan password wajib diisi!';
        } else {
            // Prepared Statement (Slide 18 & 19: Memisahkan struktur query dari input pengguna)
            $stmt = $pdo->prepare(
                'SELECT u.id, u.username, u.password_hash, u.role, p.full_name 
                 FROM users u 
                 LEFT JOIN user_profiles p ON p.user_id = u.id 
                 WHERE u.username = :username 
                 LIMIT 1'
            );
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verifikasi Password Hash & Regenerasi ID Sesi (Slide 23)
            if ($user && password_verify($password, $user['password_hash'])) {
                login_user($user);
                set_flash('success', "Selamat datang kembali, <strong>" . e($user['full_name'] ?? $user['username']) . "</strong>! Anda berhasil masuk sebagai <strong>" . strtoupper($user['role']) . "</strong>.");
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Username atau kata sandi tidak cocok. Silakan coba lagi.';
            }
        }
    }
}

$title = "Login Masuk";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> - Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            max-width: 440px;
            width: 100%;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.35);
            background: #ffffff;
            overflow: hidden;
        }
        .login-header {
            background-color: #0d6efd;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            padding: 2.2rem 2rem 1.8rem;
            text-align: center;
        }
        .demo-box {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 0.5rem;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="d-inline-flex p-3 rounded-circle bg-white bg-opacity-25 mb-3 shadow-sm">
            <i class="bi bi-boxes fs-1 text-white"></i>
        </div>
        <h4 class="fw-bold mb-1">Inventory System</h4>
        <p class="small text-white-50 mb-0">Relational Database + Advanced PHP (Mini Project 3)</p>
    </div>

    <div class="p-4 p-md-4">
        <?php render_flash(); ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="username" class="form-label fw-semibold text-secondary small">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" 
                           class="form-control bg-light border-start-0 ps-0" 
                           id="username" 
                           name="username" 
                           placeholder="Masukkan username" 
                           value="<?= e($username) ?>" 
                           required 
                           autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fw-semibold text-secondary small">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" 
                           class="form-control bg-light border-start-0 ps-0" 
                           id="password" 
                           name="password" 
                           placeholder="Masukkan kata sandi" 
                           required>
                </div>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
                </button>
            </div>
        </form>

        <!-- Akun Demo untuk Pengujian & Evaluasi Tugas -->
        <div class="demo-box p-3 mt-4 text-secondary">
            <div class="fw-bold text-dark mb-1 d-flex align-items-center">
                <i class="bi bi-info-circle-fill text-primary me-1"></i> Akun Demo untuk Pengujian:
            </div>
            <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-1">
                <span><strong>Admin:</strong> <code>admin</code> / <code>password123</code></span>
                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" onclick="setDemo('admin', 'password123')">Isi Form</button>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span><strong>Staff:</strong> <code>staff</code> / <code>password123</code></span>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="setDemo('staff', 'password123')">Isi Form</button>
            </div>
        </div>
    </div>
</div>

<script>
function setDemo(u, p) {
    document.getElementById('username').value = u;
    document.getElementById('password').value = p;
}
</script>
</body>
</html>
