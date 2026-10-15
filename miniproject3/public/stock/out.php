<?php
/**
 * Transaksi Barang Keluar
 * Implementasi Slide 26 & 27:
 * - BEGIN TRANSACTION
 * - SELECT stock ... FOR UPDATE (Pessimistic Locking mencegah race condition)
 * - Validasi quantity <= stock (Stok tidak boleh negatif!)
 * - UPDATE products SET stock = stock - quantity
 * - INSERT stock_movements (type = 'OUT')
 * - COMMIT / ROLLBACK
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_login(); // Admin dan Staff diizinkan mencatat transaksi keluar (Slide 24 & 32)

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

        // 1. Validasi Sintaktis dasar
        if ($selectedProductId <= 0) {
            $errors[] = "Silakan pilih produk yang akan dikeluarkan.";
        }

        $errQty = validate_int($quantity, 1, 1000000, 'Jumlah Barang Keluar');
        if ($errQty) {
            $errors[] = $errQty;
        }

        // 2. Eksekusi Business Logic & Database Transaction (Slide 26 & 27)
        if (empty($errors)) {
            $currentUser = current_user();
            $qty = (int)$quantity;

            // Memulai transaksi database
            $pdo->beginTransaction();
            try {
                // Langkah 1: Kunci baris dengan FOR UPDATE agar data stok konsisten (Slide 26)
                $stmtLock = $pdo->prepare("SELECT id, name, sku, stock FROM products WHERE id = :id FOR UPDATE");
                $stmtLock->execute(['id' => $selectedProductId]);
                $product = $stmtLock->fetch();

                if (!$product) {
                    throw new Exception("Produk tidak ditemukan di database.");
                }

                $currentStock = (int)$product['stock'];

                // Langkah 2: Validasi Business Rule: quantity <= stock (Slide 26 & 33)
                if ($qty > $currentStock) {
                    throw new Exception(
                        "Validasi Gagal (Business Rule): Jumlah barang keluar ({$qty} unit) melebihi stok yang tersedia saat ini ({$currentStock} unit). Stok tidak boleh bernilai negatif!"
                    );
                }

                // Langkah 3: UPDATE products SET stock = stock - quantity (Slide 26)
                $stmtUpdate = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id");
                $stmtUpdate->execute([
                    'qty' => $qty,
                    'id'  => $selectedProductId,
                ]);

                // Langkah 4: INSERT stock_movements (type = 'OUT') (Slide 26)
                $stmtHist = $pdo->prepare("
                    INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
                    VALUES (:p_id, :u_id, 'OUT', :qty, :notes)
                ");
                $stmtHist->execute([
                    'p_id'  => $selectedProductId,
                    'u_id'  => $currentUser['id'],
                    'qty'   => $qty,
                    'notes' => !empty($notes) ? $notes : 'Pengeluaran barang / distribusi operasional',
                ]);

                // Langkah 5: Commit transaksi
                $pdo->commit();

                $remainingStock = $currentStock - $qty;
                set_flash('success', "Pengeluaran barang berhasil dicatat! Stok <strong>" . e($product['name']) . "</strong> berkurang <strong>-{$qty}</strong> unit (Sisa stok: <strong>{$remainingStock}</strong> unit). Histori transaksi tersimpan.");
                header('Location: history.php');
                exit;

            } catch (Throwable $e) {
                // Batalkan seluruh perubahan jika validasi gagal atau error terjadi (Slide 26 & 27)
                $pdo->rollBack();
                $errors[] = $e->getMessage();
            }
        }
    }
}

$title = "Transaksi Barang Keluar (OUT)";
require __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0 text-danger">
                    <i class="bi bi-arrow-up-circle-fill me-2"></i>Pencatatan Barang Keluar (OUT)
                </h3>
                <p class="text-muted small">Keluarkan barang dari gudang dengan validasi stok agar tidak bernilai negatif (Slide 26).</p>
            </div>
            <a href="history.php" class="btn btn-outline-secondary">Riwayat Mutasi</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm mb-3">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i> Terjadi kegagalan validasi / transaksi:</div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $e): ?>
                        <li><?= e($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 border-top border-4 border-danger">
            <div class="card-body p-4">
                <form method="POST" action="out.php">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="product_id" class="form-label fw-semibold">Pilih Produk <span class="text-danger">*</span></label>
                        <select class="form-select" id="product_id" name="product_id" required onchange="updateProductStockInfo(this)">
                            <option value="">-- Pilih Produk yang Hendak Dikeluarkan --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" 
                                        data-stock="<?= $p['stock'] ?>"
                                        data-min="<?= $p['minimum_stock'] ?>"
                                        <?= ($selectedProductId === (int)$p['id']) ? 'selected' : '' ?>>
                                    [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Tersedia: <?= $p['stock'] ?> unit)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Info Box Realtime Stok Tersedia -->
                    <div id="stockInfoBox" class="alert alert-warning border d-none mb-3 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-info-circle me-1"></i> Stok Tersedia Saat Ini:</span>
                            <span class="fs-5 fw-bold" id="currentStockDisplay">-</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="quantity" class="form-label fw-semibold">Jumlah Barang Keluar (Unit) <span class="text-danger">*</span></label>
                        <input type="number" min="1" class="form-control form-control-lg" id="quantity" name="quantity" value="<?= e($quantity) ?>" placeholder="Contoh: 3" required>
                        <div class="form-text text-danger">Peringatan: Jumlah tidak boleh melebihi stok yang tersedia (Business Rule).</div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label fw-semibold">Keperluan / Divisi Penerima / No. SPK</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Contoh: Permintaan unit kerja Divisi Marketing untuk operasional lapangan"><?= e($notes) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= base_url('dashboard.php') ?>" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-danger px-4 py-2 fw-semibold">
                            <i class="bi bi-box-arrow-up me-1"></i> Simpan Transaksi Barang Keluar
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
