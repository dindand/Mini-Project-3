<?php
/**
 * Detail Produk & Histori Mutasi Per-Produk
 * Menampilkan Relasi Many-to-Many Supplier dan Histori Mutasi Transaksi
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_login();

$id = (int)($_GET['id'] ?? 0);

// Ambil detail produk & relasi kategori (One-to-Many)
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.description AS category_desc
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.id = :id
");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Ambil relasi supplier (Many-to-Many via product_suppliers, Slide 8)
$stmtSuppliers = $pdo->prepare("
    SELECT s.id, s.name, s.contact_person, s.phone, s.email, ps.supplier_price
    FROM product_suppliers ps
    JOIN suppliers s ON s.id = ps.supplier_id
    WHERE ps.product_id = :id
    ORDER BY s.name ASC
");
$stmtSuppliers->execute(['id' => $id]);
$suppliers = $stmtSuppliers->fetchAll();

// Ambil histori mutasi khusus produk ini (Slide 10)
$stmtHistory = $pdo->prepare("
    SELECT sm.id, sm.type, sm.quantity, sm.notes, sm.created_at,
           u.username, prof.full_name
    FROM stock_movements sm
    JOIN users u ON u.id = sm.user_id
    LEFT JOIN user_profiles prof ON prof.user_id = u.id
    WHERE sm.product_id = :id
    ORDER BY sm.created_at DESC
");
$stmtHistory->execute(['id' => $id]);
$history = $stmtHistory->fetchAll();

// Hitung total IN dan total OUT khusus produk ini (Slide 28)
$stmtCalc = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END) AS total_in,
        SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END) AS total_out
    FROM stock_movements
    WHERE product_id = :id
");
$stmtCalc->execute(['id' => $id]);
$stats = $stmtCalc->fetch();
$totalIn = (int)($stats['total_in'] ?? 0);
$totalOut = (int)($stats['total_out'] ?? 0);

$isLow = (int)$product['stock'] <= (int)$product['minimum_stock'];
$title = "Detail Produk: " . $product['name'];
require __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php">Produk</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($product['sku']) ?></li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-0"><?= e($product['name']) ?></h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('stock/in.php?product_id=' . $product['id']) ?>" class="btn btn-success">
            <i class="bi bi-arrow-down-circle-fill me-1"></i> Barang Masuk
        </a>
        <a href="<?= base_url('stock/out.php?product_id=' . $product['id']) ?>" class="btn btn-danger">
            <i class="bi bi-arrow-up-circle-fill me-1"></i> Barang Keluar
        </a>
        <?php if (is_admin()): ?>
        <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline-secondary">Kembali</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Informasi Produk & Stok -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <span class="fw-bold"><i class="bi bi-info-circle text-primary me-2"></i>Informasi & Spesifikasi</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">SKU (Kode Unik)</span>
                        <span class="fs-5 fw-bold font-monospace"><?= e($product['sku']) ?></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Kategori</span>
                        <span class="badge bg-secondary fs-6 mt-1"><?= e($product['category_name']) ?></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Harga Satuan</span>
                        <span class="fs-5 fw-bold text-primary"><?= format_rupiah($product['price']) ?></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Valuasi Stok Tersedia</span>
                        <span class="fs-5 fw-bold text-success">
                            <?= format_rupiah($product['stock'] * $product['price']) ?>
                        </span>
                    </div>
                    <div class="col-12"><hr class="my-1"></div>
                    <div class="col-sm-4">
                        <span class="text-muted small d-block">Sisa Stok Fisik</span>
                        <div class="d-flex align-items-center mt-1">
                            <span class="display-6 fw-bold <?= $isLow ? 'text-danger' : 'text-dark' ?> me-2">
                                <?= number_format($product['stock']) ?>
                            </span>
                            <span class="small text-muted">unit</span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <span class="text-muted small d-block">Batas Minimum Stok</span>
                        <div class="d-flex align-items-center mt-1">
                            <span class="display-6 fw-bold text-muted me-2">
                                <?= number_format($product['minimum_stock']) ?>
                            </span>
                            <span class="small text-muted">unit</span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <span class="text-muted small d-block">Status Ketersediaan</span>
                        <?php if ($isLow): ?>
                            <span class="badge bg-danger fs-6 mt-2"><i class="bi bi-exclamation-triangle me-1"></i> Perlu Restock</span>
                        <?php else: ?>
                            <span class="badge bg-success fs-6 mt-2"><i class="bi bi-check-circle me-1"></i> Stok Cukup</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="alert alert-light border mt-4 mb-0">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <span class="small text-muted d-block">Akumulasi Barang Masuk</span>
                            <strong class="text-success fs-5">+<?= number_format($totalIn) ?></strong> unit
                        </div>
                        <div class="col-6">
                            <span class="small text-muted d-block">Akumulasi Barang Keluar</span>
                            <strong class="text-danger fs-5">-<?= number_format($totalOut) ?></strong> unit
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Relasi Supplier (Many-to-Many) -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-truck text-info me-2"></i>Supplier Pemasok (Many-to-Many)</span>
                <?php if (is_admin()): ?>
                <a href="<?= base_url('product-suppliers/create.php?product_id=' . $product['id']) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus"></i> Hubungkan
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($suppliers)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-link-45deg fs-2 d-block mb-1 text-secondary"></i>
                        Belum ada supplier yang ditautkan ke produk ini.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($suppliers as $sup): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-0 fw-bold"><?= e($sup['name']) ?></h6>
                                <small class="text-muted d-block">
                                    Kontak: <?= e($sup['contact_person'] ?: '-') ?> | Telp: <?= e($sup['phone'] ?: '-') ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-light text-primary border fw-semibold">
                                    <?= $sup['supplier_price'] ? format_rupiah($sup['supplier_price']) : 'Harga N/A' ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Riwayat Mutasi Stok Produk Ini -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <span class="fw-bold"><i class="bi bi-clock-history text-secondary me-2"></i>Riwayat Perubahan Stok (Audit Trail)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Waktu Transaksi</th>
                        <th>Tipe</th>
                        <th>Jumlah</th>
                        <th>User Pelaksana</th>
                        <th>Catatan / Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">Belum ada catatan mutasi untuk produk ini.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td class="small text-muted"><?= format_datetime($h['created_at']) ?></td>
                            <td>
                                <?php if ($h['type'] === 'IN'): ?>
                                    <span class="badge badge-in fw-semibold">IN (Masuk)</span>
                                <?php else: ?>
                                    <span class="badge badge-out fw-semibold">OUT (Keluar)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="<?= $h['type'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                                    <?= $h['type'] === 'IN' ? '+' : '-' ?><?= number_format($h['quantity']) ?> unit
                                </strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= e($h['full_name'] ?? $h['username']) ?>
                                </span>
                            </td>
                            <td class="text-muted small"><?= e($h['notes'] ?: '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
