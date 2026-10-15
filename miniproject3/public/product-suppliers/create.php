<?php
/**
 * Tambah Relasi Many-to-Many Produk dan Supplier
 * Menyimpan pasangan (product_id, supplier_id) dengan composite primary key (Slide 8)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_role('admin');

$preProductId = !empty($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$errors = [];
$productId = $preProductId;
$supplierId = 0;
$supplierPrice = '';

$products = $pdo->query("SELECT id, sku, name FROM products ORDER BY name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = "Token formulir kadaluarsa. Silakan ulangi.";
    } else {
        $productId = (int)($_POST['product_id'] ?? 0);
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $supplierPrice = trim($_POST['supplier_price'] ?? '');

        if ($productId <= 0) $errors[] = "Silakan pilih produk yang valid.";
        if ($supplierId <= 0) $errors[] = "Silakan pilih supplier yang valid.";

        if ($supplierPrice !== '') {
            $errPrice = validate_decimal($supplierPrice, 0.0, 'Harga Beli Supplier');
            if ($errPrice) $errors[] = $errPrice;
        }

        // Cek composite primary key: apakah pasangan sudah terdaftar
        if (empty($errors)) {
            $stmtCheck = $pdo->prepare("SELECT product_id FROM product_suppliers WHERE product_id = :p_id AND supplier_id = :s_id");
            $stmtCheck->execute(['p_id' => $productId, 's_id' => $supplierId]);
            if ($stmtCheck->fetch()) {
                $errors[] = "Relasi antara produk dan supplier ini sudah ada sebelumnya (Composite Primary Key).";
            }
        }

        if (empty($errors)) {
            try {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO product_suppliers (product_id, supplier_id, supplier_price)
                    VALUES (:p_id, :s_id, :price)
                ");
                $stmtInsert->execute([
                    'p_id'  => $productId,
                    's_id'  => $supplierId,
                    'price' => ($supplierPrice !== '') ? (float)$supplierPrice : null,
                ]);

                set_flash('success', "Relasi produk dan supplier berhasil ditambahkan ke junction table.");
                header('Location: index.php?product_id=' . $productId);
                exit;
            } catch (PDOException $e) {
                $errors[] = "Gagal menghubungkan produk dan supplier: " . $e->getMessage();
            }
        }
    }
}

$title = "Hubungkan Produk & Supplier";
require __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold mb-0"><i class="bi bi-link-45deg text-warning me-2"></i>Tautkan Produk & Supplier</h3>
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
                        <label for="product_id" class="form-label fw-semibold">Pilih Produk <span class="text-danger">*</span></label>
                        <select class="form-select" id="product_id" name="product_id" required>
                            <option value="">-- Pilih Produk --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ((int)$productId === (int)$p['id']) ? 'selected' : '' ?>>
                                    [<?= e($p['sku']) ?>] <?= e($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="supplier_id" class="form-label fw-semibold">Pilih Supplier <span class="text-danger">*</span></label>
                        <select class="form-select" id="supplier_id" name="supplier_id" required>
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ((int)$supplierId === (int)$s['id']) ? 'selected' : '' ?>>
                                    <?= e($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="supplier_price" class="form-label fw-semibold">Harga Pasokan Supplier (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" step="1000" min="0" class="form-control" id="supplier_price" name="supplier_price" value="<?= e($supplierPrice) ?>" placeholder="Contoh: 450000">
                        </div>
                        <div class="form-text">Biaya pasokan / harga beli dari supplier ini ke inventaris.</div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <a href="index.php" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Relasi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
