<?php
/**
 * Transaksi Barang Masuk (Restock)
 * Implementasi Slide 25 & 27:
 * - Validasi product_id dan quantity > 0
 * - Database Transaction (beginTransaction, commit, rollBack)
 * - Tambah stok produk: UPDATE products SET stock = stock + quantity
 * - Catat histori: INSERT INTO stock_movements (type = 'IN')
 * - Simpan user pelaksana (user_id)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_login(); // Admin dan Staff diizinkan mencatat transaksi (Slide 24 & 32)

$selectedProductId = (int)($_GET['product_id'] ?? 0);
$errors = [];
$quantity = '';
$notes = '';

$products = $pdo->query("SELECT id, sku, name, stock, minimum_stock FROM products ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = "Token formulir kadaluarsa. Silakan ulangi.";
    } else {
        $selectedProductId = (int)($_POST['product_id'] ?? 0);
        $quantity = trim($_POST['quantity'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // 1. Syntactic & Semantic Validation (Slide 21)
        if ($selectedProductId <= 0) {
            $errors[] = "Silakan pilih produk yang akan ditambahkan stoknya.";
        } else {
            $stmtP = $pdo->prepare("SELECT id, name, sku, stock FROM products WHERE id = :id");
            $stmtP->execute(['id' => $selectedProductId]);
            $productTarget = $stmtP->fetch();
            if (!$productTarget) {
                $errors[] = "Produk yang dipilih tidak ditemukan dalam database.";
            }
        }

        $errQty = validate_int($quantity, 1, 1000000, 'Jumlah Barang Masuk');
        if ($errQty) {
            $errors[] = $errQty;
        }

        // 2. Eksekusi Database Transaction (Slide 25 & 27)
        if (empty($errors)) {
            $currentUser = current_user();
            $qty = (int)$quantity;

            $pdo->beginTransaction();
            try {
                // a. Tambah stok produk
                $stmtUpdate = $pdo->prepare("UPDATE products SET stock = stock + :qty WHERE id = :id");
                $stmtUpdate->execute([
                    'qty' => $qty,
                    'id'  => $selectedProductId,
                ]);

                // b. Catat histori mutasi stok (stock_movements)
                $stmtInsertHist = $pdo->prepare("
                    INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
                    VALUES (:p_id, :u_id, 'IN', :qty, :notes)
                ");
                $stmtInsertHist->execute([
                    'p_id'  => $selectedProductId,
                    'u_id'  => $currentUser['id'],
                    'qty'   => $qty,
                    'notes' => !empty($notes) ? $notes : 'Penerimaan barang masuk (Restock)',
                ]);

                // c. Commit transaksi secara utuh
                $pdo->commit();

                $newTotal = $productTarget['stock'] + $qty;
                set_flash('success', "Transaksi berhasil! Stok produk <strong>" . e($productTarget['name']) . "</strong> bertambah <strong>+{$qty}</strong> unit (Stok sekarang: <strong>{$newTotal}</strong> unit). Histori transaksi tersimpan.");
                header('Location: history.php');
                exit;

            } catch (Throwable $e) {
                // d. Batalkan semua perubahan jika terjadi error
                $pdo->rollBack();
                $errors[] = "Transaksi gagal dibatalkan (Rollback): " . $e->getMessage();
            }
        }
    }
}

$title = "Transaksi Barang Masuk (IN)";
require __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0 text-success">
                    <i class="bi bi-arrow-down-circle-fill me-2"></i>Pencatatan Barang Masuk (IN)
                </h3>
                <p class="text-muted small">Tambah persediaan barang gudang melalui transaksi atomik (ACID).</p>
            </div>
            <a href="history.php" class="btn btn-outline-secondary">Riwayat Mutasi</a>
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

        <div class="card shadow-sm border-0 border-top border-4 border-success">
            <div class="card-body p-4">
                <form method="POST" action="in.php">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="product_id" class="form-label fw-semibold">Pilih Produk <span class="text-danger">*</span></label>
                        <select class="form-select" id="product_id" name="product_id" required onchange="updateProductStockInfo(this)">
                            <option value="">-- Pilih Produk untuk Ditambahkan Stoknya --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" 
                                        data-stock="<?= $p['stock'] ?>"
                                        data-min="<?= $p['minimum_stock'] ?>"
                                        <?= ($selectedProductId === (int)$p['id']) ? 'selected' : '' ?>>
                                    [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Sisa: <?= $p['stock'] ?> unit)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Info Box Realtime -->
                    <div id="stockInfoBox" class="alert alert-light border d-none mb-3 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Sisa Stok Fisik Saat Ini:</span>
                            <span class="fs-5 fw-bold" id="currentStockDisplay">-</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="quantity" class="form-label fw-semibold">Jumlah Barang Masuk (Unit) <span class="text-danger">*</span></label>
                        <input type="number" min="1" class="form-control form-control-lg" id="quantity" name="quantity" value="<?= e($quantity) ?>" placeholder="Contoh: 10" required>
                        <div class="form-text">Jumlah unit harus lebih dari 0 (Slide 21).</div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label fw-semibold">Catatan / No. PO / Referensi Pengadaan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Contoh: Restock dari supplier PT Sumber Jaya Abadi, PO-2026-1002"><?= e($notes) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= base_url('dashboard.php') ?>" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-success px-4 py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Simpan Transaksi Barang Masuk
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function updateProductStockInfo(select) {
    const box = document.getElementById('stockInfoBox');
    const display = document.getElementById('currentStockDisplay');
    const selectedOption = select.options[select.selectedIndex];
    
    if (select.value) {
        const stock = selectedOption.getAttribute('data-stock');
        display.textContent = stock + ' Unit';
        box.classList.remove('d-none');
    } else {
        box.classList.add('d-none');
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('product_id');
    if (sel.value) updateProductStockInfo(sel);
});
</script>

<?php require __DIR__ . '/../../views/footer.php'; ?>
