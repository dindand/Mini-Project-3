<?php
/**
 * Edit Data Produk
 * Hanya untuk Role Admin (Slide 24 & 33)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$errors = [];

$sku = $product['sku'];
$name = $product['name'];
$categoryId = $product['category_id'];
$price = $product['price'];
$minStock = $product['minimum_stock'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = "Token keamanan form tidak valid. Silakan ulangi.";
    } else {
        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $price = trim($_POST['price'] ?? '');
        $minStock = trim($_POST['minimum_stock'] ?? '5');

        // Validasi Semantic & Syntactic
        $errSku = validate_sku($sku, $pdo, $id);
        if ($errSku) $errors[] = $errSku;

        $errName = validate_required($name, 'Nama Produk');
        if ($errName) $errors[] = $errName;

        $stmtCat = $pdo->prepare("SELECT id FROM categories WHERE id = :id");
        $stmtCat->execute(['id' => $categoryId]);
        if (!$stmtCat->fetch()) {
            $errors[] = "Kategori yang dipilih tidak valid di database.";
        }

        $errPrice = validate_decimal($price, 0.0, 'Harga Produk');
        if ($errPrice) $errors[] = $errPrice;

        $errMin = validate_int($minStock, 0, null, 'Batas Stok Minimum');
        if ($errMin) $errors[] = $errMin;

        if (empty($errors)) {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE products 
                    SET category_id = :category_id,
                        sku = :sku,
                        name = :name,
                        price = :price,
                        minimum_stock = :minimum_stock
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    'category_id'   => $categoryId,
                    'sku'           => $sku,
                    'name'          => $name,
                    'price'         => (float)$price,
                    'minimum_stock' => (int)$minStock,
                    'id'            => $id,
                ]);

                set_flash('success', "Data produk <strong>" . e($name) . "</strong> berhasil diperbarui.");
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = "Gagal memperbarui produk: " . $e->getMessage();
            }
        }
    }
}

$title = "Edit Produk: " . $product['name'];
require __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Data Produk</h3>
                <p class="text-muted small">Perbarui parameter produk ID #<?= $id ?>.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm mb-4">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i> Terjadi kesalahan validasi:</div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="edit.php?id=<?= $id ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="sku" class="form-label fw-semibold">SKU Produk <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="sku" name="sku" value="<?= e($sku) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="category_id" class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ((int)$categoryId === (int)$cat['id']) ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="name" class="form-label fw-semibold">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= e($name) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="price" class="form-label fw-semibold">Harga Satuan (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" step="1000" min="0" class="form-control" id="price" name="price" value="<?= e($price) ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="minimum_stock" class="form-label fw-semibold">Batas Minimum Stok</label>
                            <input type="number" min="0" class="form-control" id="minimum_stock" name="minimum_stock" value="<?= e($minStock) ?>">
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info d-flex align-items-center mb-0 mt-2">
                                <i class="bi bi-info-circle-fill fs-5 me-2 flex-shrink-0"></i>
                                <div>
                                    <strong>Aturan Bisnis Stok:</strong> Stok saat ini adalah <strong><?= number_format($product['stock']) ?> unit</strong>. Jumlah stok tidak dapat diedit secara manual di sini, melainkan harus melalui menu <a href="<?= base_url('stock/in.php?product_id=' . $id) ?>" class="alert-link">Barang Masuk</a> atau <a href="<?= base_url('stock/out.php?product_id=' . $id) ?>" class="alert-link">Barang Keluar</a> agar riwayat audit mutasi selalu akurat.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
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
