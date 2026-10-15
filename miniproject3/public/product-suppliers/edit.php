<?php
/**
 * Edit Harga Pasokan Supplier pada Junction Table
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_role('admin');

$productId = (int)($_GET['product_id'] ?? 0);
$supplierId = (int)($_GET['supplier_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT ps.*, p.name AS product_name, p.sku, s.name AS supplier_name
    FROM product_suppliers ps
    JOIN products p ON p.id = ps.product_id
    JOIN suppliers s ON s.id = ps.supplier_id
    WHERE ps.product_id = :p_id AND ps.supplier_id = :s_id
");
$stmt->execute(['p_id' => $productId, 's_id' => $supplierId]);
$relation = $stmt->fetch();

if (!$relation) {
    set_flash('danger', 'Data relasi produk dan supplier tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$errors = [];
$supplierPrice = $relation['supplier_price'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = "Token formulir kadaluarsa. Silakan ulangi.";
    } else {
        $supplierPrice = trim($_POST['supplier_price'] ?? '');

        if ($supplierPrice !== '') {
            $errPrice = validate_decimal($supplierPrice, 0.0, 'Harga Beli Supplier');
            if ($errPrice) $errors[] = $errPrice;
        }

        if (empty($errors)) {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE product_suppliers
                    SET supplier_price = :price
                    WHERE product_id = :p_id AND supplier_id = :s_id
                ");
                $stmtUpdate->execute([
                    'price' => ($supplierPrice !== '') ? (float)$supplierPrice : null,
                    'p_id'  => $productId,
                    's_id'  => $supplierId,
                ]);

                set_flash('success', "Harga pasokan supplier berhasil diperbarui.");
                header('Location: index.php?product_id=' . $productId);
                exit;
            } catch (PDOException $e) {
                $errors[] = "Gagal memperbarui harga pasokan: " . $e->getMessage();
            }
        }
    }
}

$title = "Ubah Harga Pasokan Supplier";
require __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold mb-0"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Harga Pasokan</h3>
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
                <form method="POST" action="edit.php?product_id=<?= $productId ?>&supplier_id=<?= $supplierId ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Produk Terkait</label>
                        <div class="form-control bg-light">
                            <strong><?= e($relation['product_name']) ?></strong> 
                            <span class="badge bg-secondary font-monospace ms-2"><?= e($relation['sku']) ?></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Supplier Terkait</label>
                        <div class="form-control bg-light">
                            <strong><?= e($relation['supplier_name']) ?></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="supplier_price" class="form-label fw-semibold">Harga Pasokan / Beli (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" step="1000" min="0" class="form-control" id="supplier_price" name="supplier_price" value="<?= e($supplierPrice) ?>" placeholder="Contoh: 450000">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <a href="index.php" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
