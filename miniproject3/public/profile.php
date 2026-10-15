<?php
/**
 * Profil Pengguna & Ganti Password Mandiri
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/flash.php';
require_once __DIR__ . '/../helpers/validation.php';

require_login();

$userSession = current_user();
$userId = (int)$userSession['id'];

// Ambil profil lengkap dari database
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.role, u.password_hash,
           p.full_name, p.phone, p.address
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE u.id = :id
");
$stmt->execute(['id' => $userId]);
$userData = $stmt->fetch();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = "Token formulir kadaluarsa. Silakan ulangi.";
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';

        $errName = validate_required($fullName, 'Nama Lengkap');
        if ($errName) $errors[] = $errName;

        // Validasi jika ingin ganti password
        $changePassword = false;
        if (!empty($oldPassword) || !empty($newPassword)) {
            if (empty($oldPassword)) {
                $errors[] = "Kata sandi lama wajib diisi jika ingin mengganti kata sandi.";
            } elseif (!password_verify($oldPassword, $userData['password_hash'])) {
                $errors[] = "Kata sandi lama yang Anda masukkan tidak sesuai.";
            } elseif (strlen($newPassword) < 6) {
                $errors[] = "Kata sandi baru minimal 6 karakter.";
            } else {
                $changePassword = true;
            }
        }

        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                // Update password jika diminta
                if ($changePassword) {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmtPass = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                    $stmtPass->execute(['hash' => $newHash, 'id' => $userId]);
                }

                // Update profil (One-to-One)
                $stmtCheckProf = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = :id");
                $stmtCheckProf->execute(['id' => $userId]);
                if ($stmtCheckProf->fetch()) {
                    $stmtProf = $pdo->prepare("UPDATE user_profiles SET full_name = :fn, phone = :ph, address = :addr WHERE user_id = :id");
                    $stmtProf->execute([
                        'fn'   => $fullName,
                        'ph'   => $phone,
                        'addr' => $address,
                        'id'   => $userId,
                    ]);
                } else {
                    $stmtProf = $pdo->prepare("INSERT INTO user_profiles (user_id, full_name, phone, address) VALUES (:id, :fn, :ph, :addr)");
                    $stmtProf->execute([
                        'id'   => $userId,
                        'fn'   => $fullName,
                        'ph'   => $phone,
                        'addr' => $address,
                    ]);
                }

                $_SESSION['user']['full_name'] = $fullName;
                $pdo->commit();

                set_flash('success', 'Profil Anda berhasil diperbarui.');
                header('Location: profile.php');
                exit;

            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = "Gagal memperbarui profil: " . $e->getMessage();
            }
        }
    }
}

$title = "Profil Pengguna";
require __DIR__ . '/../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <h3 class="fw-bold mb-3"><i class="bi bi-person-badge text-primary me-2"></i>Profil Pengguna</h3>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm mb-3">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $e): ?>
                        <li><?= e($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="profile.php">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Username & Peran (Role)</label>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-5 fw-bold font-monospace"><?= e($userData['username']) ?></span>
                            <span class="badge <?= $userData['role'] === 'admin' ? 'bg-danger' : 'bg-primary' ?> text-uppercase">
                                <?= e($userData['role']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="full_name" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($userData['full_name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label fw-semibold">Nomor Telepon</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?= e($userData['phone'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label fw-semibold">Alamat</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?= e($userData['address'] ?? '') ?></textarea>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold text-dark mb-2">Ganti Kata Sandi (Opsional)</h6>
                    <p class="small text-muted mb-3">Isi hanya jika Anda ingin memperbarui kata sandi akun.</p>

                    <div class="mb-3">
                        <label for="old_password" class="form-label fw-semibold">Kata Sandi Saat Ini</label>
                        <input type="password" class="form-control" id="old_password" name="old_password" placeholder="Masukkan kata sandi lama">
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-semibold">Kata Sandi Baru</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Minimal 6 karakter">
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= base_url('dashboard.php') ?>" class="btn btn-outline-secondary">Kembali</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
