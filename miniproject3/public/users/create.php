<?php
/**
 * Tambah Pengguna & Profil Baru
 * Implementasi Slide 6 (One-to-One users <-> user_profiles) & Slide 23 (Password Hashing)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_role('admin');

$errors = [];
$username = '';
$fullName = '';
$role = 'staff';
$phone = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = "Token formulir kadaluarsa. Silakan ulangi.";
    } else {
        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? 'staff');
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        // Validasi Syntactic & Semantic
        $errUser = validate_required($username, 'Username');
        if ($errUser) $errors[] = $errUser;

        if (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = "Username harus memiliki panjang antara 3 sampai 50 karakter.";
        }

        if (strlen($password) < 6) {
            $errors[] = "Kata sandi minimal 6 karakter.";
        }

        $errName = validate_required($fullName, 'Nama Lengkap');
        if ($errName) $errors[] = $errName;

        if (!in_array($role, ['admin', 'staff'], true)) {
            $errors[] = "Peran (role) yang dipilih tidak valid.";
        }

        // Cek keunikan username
        if (empty($errors)) {
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = :u");
            $stmtCheck->execute(['u' => $username]);
            if ($stmtCheck->fetch()) {
                $errors[] = "Username '{$username}' sudah terdaftar. Gunakan username lain.";
            }
        }

        // Simpan User & Profile dalam Transaksi Database
        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                // Password Hashing dengan bcrypt (Slide 23)
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                // 1. Simpan tabel users
                $stmtUser = $pdo->prepare("
                    INSERT INTO users (username, password_hash, role)
                    VALUES (:username, :password_hash, :role)
                ");
                $stmtUser->execute([
                    'username'      => $username,
                    'password_hash' => $passwordHash,
                    'role'          => $role,
                ]);
                $userId = (int)$pdo->lastInsertId();

                // 2. Simpan tabel user_profiles (One-to-One relasi)
                $stmtProfile = $pdo->prepare("
                    INSERT INTO user_profiles (user_id, full_name, phone, address)
                    VALUES (:user_id, :full_name, :phone, :address)
                ");
                $stmtProfile->execute([
                    'user_id'   => $userId,
                    'full_name' => $fullName,
                    'phone'     => $phone,
                    'address'   => $address,
                ]);

                $pdo->commit();
                set_flash('success', "Akun pengguna <strong>" . e($username) . "</strong> ({$role}) berhasil dibuat beserta profilnya.");
                header('Location: index.php');
                exit;

            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = "Gagal membuat pengguna: " . $e->getMessage();
            }
        }
    }
}

$title = "Tambah Pengguna Baru";
require __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold mb-0"><i class="bi bi-person-plus-fill text-warning me-2"></i>Tambah Pengguna Baru</h3>
            <a href="index.php" class="btn btn-outline-secondary">Kembali</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm mb-3">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i> Terjadi kesalahan validasi:</div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $e): ?>
                        <li><?= e($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="create.php" novalidate>
                    <?= csrf_field() ?>

                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Informasi Akun Otentikasi (Tabel Users)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="username" class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= e($username) ?>" placeholder="Contoh: agus_gudang" required autofocus>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label fw-semibold">Peran (Role) <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff (Melihat Produk & Mencatat Mutasi)</option>
                                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin (Akses Penuh Seluruh Modul & User)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="password" class="form-label fw-semibold">Kata Sandi (Password) <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Minimal 6 karakter" required>
                            <div class="form-text">Kata sandi akan di-hash menggunakan algoritma <code>PASSWORD_BCRYPT</code> (Slide 23).</div>
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Informasi Profil (Tabel User Profiles - One-to-One)</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($fullName) ?>" placeholder="Contoh: Agus Pratama" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold">Nomor Telepon</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label fw-semibold">Alamat</label>
                            <textarea class="form-control" id="address" name="address" rows="2" placeholder="Alamat tinggal / penempatan"><?= e($address) ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="index.php" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Pengguna
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
