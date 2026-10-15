<?php
/**
 * Edit Data Supplier
 * Khusus Role Admin
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id");
$stmt->execute(['id' => $id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    set_flash('danger', 'Supplier tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$errors = [];
$name = $supplier['name'];
$contact = $supplier['contact_person'];
$phone = $supplier['phone'];
$email = $supplier['email'];
$address = $supplier['address'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = "Token formulir kadaluarsa. Silakan ulangi.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        $errName = validate_required($name, 'Nama Supplier');
        if ($errName) $errors[] = $errName;

        if (empty($errors)) {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE suppliers 
                    SET name = :name,
                        contact_person = :contact_person,
                        phone = :phone,
                        email = :email,
                        address = :address
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    'name'           => $name,
                    'contact_person' => $contact,
                    'phone'          => $phone,
                    'email'          => $email,
                    'address'        => $address,
                    'id'             => $id,
                ]);

                set_flash('success', "Data supplier <strong>" . e($name) . "</strong> berhasil diperbarui.");
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = "Gagal memperbarui supplier: " . $e->getMessage();
            }
        }
    }
}

$title = "Edit Supplier: " . $supplier['name'];
require __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold mb-0"><i class="bi bi-pencil-square text-info me-2"></i>Edit Data Supplier</h3>
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
                <form method="POST" action="edit.php?id=<?= $id ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Nama Supplier / Perusahaan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($name) ?>" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="contact_person" class="form-label fw-semibold">Nama PIC / Kontak Person</label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?= e($contact) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold">Nomor Telepon / WA</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= e($phone) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Alamat Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label fw-semibold">Alamat Kantor / Gudang</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?= e($address) ?></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
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
