<?php
/**
 * Tambah Kategori Baru
 * Hanya untuk Role Admin
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_role('admin');

$errors = [];
$name = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = "Token formulir kadaluarsa. Silakan ulangi.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $errName = validate_required($name, 'Nama Kategori');
        if ($errName) $errors[] = $errName;

        // Cek keunikan nama kategori
        if (empty($errors)) {
            $stmtCheck = $pdo->prepare("SELECT id FROM categories WHERE name = :name");
            $stmtCheck->execute(['name' => $name]);
            if ($stmtCheck->fetch()) {
                $errors[] = "Kategori dengan nama '{$name}' sudah ada.";
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :description)");
                $stmt->execute([
                    'name'        => $name,
                    'description' => $description,
                ]);

                set_flash('success', "Kategori <strong>" . e($name) . "</strong> berhasil ditambahkan.");
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = "Gagal menyimpan kategori: " . $e->getMessage();
            }
        }
    }
}

$title = "Tambah Kategori";
require __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold mb-0"><i class="bi bi-plus-circle text-success me-2"></i>Tambah Kategori</h3>
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
                <form method="POST" action="create.php">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($name) ?>" placeholder="Misal: Peralatan Kantor" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Keterangan singkat mengenai kategori"><?= e($description) ?></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <a href="index.php" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-success px-4">
                            <i class="bi bi-save me-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
