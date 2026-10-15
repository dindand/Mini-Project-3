<?php
/**
 * Tambah Produk Baru
 * Hanya untuk Role Admin (Slide 24 & 33)
 * Menggunakan Validasi Server-Side & Transaksi Database (Slide 21 & 27)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_role('admin');

$errors = [];
$sku = '';
$name = '';
$categoryId = '';
$price = '';
$stock = '0';
$minStock = '5';

// Ambil kategori untuk pilihan dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = "Token keamanan form tidak valid. Silakan ulangi.";
    } else {
        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $price = trim($_POST['price'] ?? '');
        $stock = trim($_POST['stock'] ?? '0');
        $minStock = trim($_POST['minimum_stock'] ?? '5');
        $notes = trim($_POST['notes'] ?? 'Stok awal produk baru didaftarkan');

        // 1. Validasi Sintaktis & Semantik (Slide 21 & 33)
        $errSku = validate_sku($sku, $pdo);
        if ($errSku) $errors[] = $errSku;

        $errName = validate_required($name, 'Nama Produk');
        if ($errName) $errors[] = $errName;

        // Validasi Kategori
        $stmtCat = $pdo->prepare("SELECT id FROM categories WHERE id = :id");
        $stmtCat->execute(['id' => $categoryId]);
        if (!$stmtCat->fetch()) {
            $errors[] = "Kategori yang dipilih tidak valid di database.";
        }

        // Validasi Harga (tidak boleh negatif)
        $errPrice = validate_decimal($price, 0.0, 'Harga Produk');
        if ($errPrice) $errors[] = $errPrice;

        // Validasi Stok Awal (tidak boleh negatif)
        $errStock = validate_int($stock, 0, null, 'Stok Awal');
        if ($errStock) $errors[] = $errStock;

        // Validasi Stok Minimum
        $errMin = validate_int($minStock, 0, null, 'Batas Stok Minimum');
        if ($errMin) $errors[] = $errMin;

        // 2. Jika valid, simpan dengan Database Transaction (Slide 27)
        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO products (category_id, sku, name, price, stock, minimum_stock)
                    VALUES (:category_id, :sku, :name, :price, :stock, :minimum_stock)
                ");
                $stmtInsert->execute([
                    'category_id'   => $categoryId,
                    'sku'           => $sku,
                    'name'          => $name,
                    'price'         => (float)$price,
                    'stock'         => (int)$stock,
                    'minimum_stock' => (int)$minStock,
                ]);

                $newProductId = (int)$pdo->lastInsertId();

                // Jika ada stok awal > 0, catat mutasi masuk ke stock_movements
                if ((int)$stock > 0) {
                    $currentUser = current_user();
                    $stmtMove = $pdo->prepare("
                        INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
                        VALUES (:product_id, :user_id, 'IN', :quantity, :notes)
                    ");
                    $stmtMove->execute([
                        'product_id' => $newProductId,
                        'user_id'    => $currentUser['id'],
                        'quantity'   => (int)$stock,
                        'notes'      => $notes,
                    ]);
                }

                $pdo->commit();
                set_flash('success', "Produk <strong>" . e($name) . "</strong> ({$sku}) berhasil ditambahkan beserta pencatatan stok.");
                header('Location: index.php');
                exit;

            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = "Gagal menyimpan produk: " . $e->getMessage();
            }
        }
    }
}

$title = "Tambah Produk Baru";
require __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0"><i class="bi bi-plus-circle text-primary me-2"></i>Tambah Produk Baru</h3>
                <p class="text-muted small">Tambahkan produk baru ke dalam katalog inventory.</p>
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
                <form method="POST" action="create.php" novalidate>
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="sku" class="form-label fw-semibold">SKU Produk <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="sku" name="sku" placeholder="Misal: PRD-ACC-004" value="<?= e($sku) ?>" required>
                            <div class="form-text">SKU harus unik di seluruh sistem (Business Rule).</div>
                        </div>

                        <div class="col-md-6">
                            <label for="category_id" class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ((int)$categoryId === (int)$cat['id']) ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="name" class="form-label fw-semibold">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Misal: Keyboard Logitech K120" value="<?= e($name) ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label for="price" class="form-label fw-semibold">Harga Satuan (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" step="1000" min="0" class="form-control" id="price" name="price" placeholder="0" value="<?= e($price) ?>" required>
                            </div>
                            <div class="form-text">Tidak boleh bernilai negatif.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="stock" class="form-label fw-semibold">Stok Awal (Unit)</label>
                            <input type="number" min="0" class="form-control" id="stock" name="stock" value="<?= e($stock) ?>">
                            <div class="form-text">Akan dicatat otomatis sebagai IN.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="minimum_stock" class="form-label fw-semibold">Batas Minimum Stok</label>
                            <input type="number" min="0" class="form-control" id="minimum_stock" name="minimum_stock" value="<?= e($minStock) ?>">
                            <div class="form-text">Peringatan saat stok &le; angka ini.</div>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label fw-semibold">Catatan Pengadaan Awal</label>
                            <input type="text" class="form-control" id="notes" name="notes" placeholder="Catatan opsional mutasi stok masuk awal" value="Stok awal registrasi produk">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="index.php" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Produk
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
