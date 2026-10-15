<?php
/**
 * Edit Pengguna & Profil (Admin Only)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_role('admin');

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.role, p.full_name, p.phone, p.address
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE u.id = :id
");
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('danger', 'Pengguna tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$errors = [];
$username = $user['username'];
$role = $user['role'];
$fullName = $user['full_name'];
$phone = $user['phone'];
$address = $user['address'];

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

        $errUser = validate_required($username, 'Username');
        if ($errUser) $errors[] = $errUser;

        $errName = validate_required($fullName, 'Nama Lengkap');
        if ($errName) $errors[] = $errName;

        if (!in_array($role, ['admin', 'staff'], true)) {
            $errors[] = "Peran (role) yang dipilih tidak valid.";
        }

        // Cek username unik (kecuali id ini)
        if (empty($errors)) {
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = :u AND id != :id");
            $stmtCheck->execute(['u' => $username, 'id' => $id]);
            if ($stmtCheck->fetch()) {
                $errors[] = "Username '{$username}' sudah digunakan oleh pengguna lain.";
            }
        }

        if (!empty($password) && strlen($password) < 6) {
            $errors[] = "Kata sandi baru minimal 6 karakter.";
        }

        // Update database
        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                // Update users
                if (!empty($password)) {
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    $stmtUpdateUser = $pdo->prepare("
                        UPDATE users 
                        SET username = :u, role = :role, password_hash = :p
                        WHERE id = :id
                    ");
                    $stmtUpdateUser->execute([
                        'u'    => $username,
                        'role' => $role,
                        'p'    => $newHash,
                        'id'   => $id,
                    ]);
                } else {
                    $stmtUpdateUser = $pdo->prepare("
                        UPDATE users 
                        SET username = :u, role = :role
                        WHERE id = :id
                    ");
                    $stmtUpdateUser->execute([
                        'u'    => $username,
                        'role' => $role,
                        'id'   => $id,
                    ]);
                }

                // Update or Insert user_profiles (One-to-One)
                $stmtCheckProf = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = :id");
                $stmtCheckProf->execute(['id' => $id]);
                if ($stmtCheckProf->fetch()) {
                    $stmtProf = $pdo->prepare("
                        UPDATE user_profiles 
                        SET full_name = :full_name, phone = :phone, address = :address 
                        WHERE user_id = :id
                    ");
                    $stmtProf->execute([
                        'full_name' => $fullName,
                        'phone'     => $phone,
                        'address'   => $address,
                        'id'        => $id,
                    ]);
                } else {
                    $stmtProf = $pdo->prepare("
                        INSERT INTO user_profiles (user_id, full_name, phone, address)
                        VALUES (:id, :full_name, :phone, :address)
                    ");
                    $stmtProf->execute([
                        'id'        => $id,
                        'full_name' => $fullName,
                        'phone'     => $phone,
                        'address'   => $address,
                    ]);
                }

                // Update session jika mengedit akun diri sendiri
                if ($id === current_user()['id']) {
                    $_SESSION['user']['username'] = $username;
                    $_SESSION['user']['role'] = $role;
                    $_SESSION['user']['full_name'] = $fullName;
                }

                $pdo->commit();
                set_flash('success', "Data pengguna <strong>" . e($username) . "</strong> berhasil diperbarui.");
                header('Location: index.php');
                exit;

            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = "Gagal memperbarui pengguna: " . $e->getMessage();
            }
        }
    }
}

$title = "Edit Pengguna: " . $user['username'];
require __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold mb-0"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Data Pengguna</h3>
            <a href="index.php" class="btn btn-outline-secondary">Kembali</a>
        </div>

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
                <form method="POST" action="edit.php?id=<?= $id ?>" novalidate>
                    <?= csrf_field() ?>

                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Akun Otentikasi</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="username" class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= e($username) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label fw-semibold">Peran (Role) <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff</option>
                                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="password" class="form-label fw-semibold">Ganti Kata Sandi (Opsional)</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Kosongkan jika tidak ingin mengubah password">
                            <div class="form-text">Biarkan kosong jika tetap menggunakan kata sandi saat ini.</div>
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Profil Pengguna</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($fullName) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold">Nomor Telepon</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= e($phone) ?>">
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label fw-semibold">Alamat</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?= e($address) ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="index.php" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
