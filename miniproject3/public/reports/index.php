<?php
/**
 * Modul Laporan Inventory
 * Implementasi Slide 28 (Query Agregat Mutasi per Produk), Slide 14 & 15 (GROUP BY, SUM, AVG, COUNT, HAVING)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_login();

// 1. Laporan Arus Mutasi Per Produk (Sesuai Query Slide 28)
$stmtReportProduct = $pdo->query("
    SELECT 
        p.id, p.sku, p.name, p.price, p.stock, p.minimum_stock,
        c.name AS category_name,
        COALESCE(SUM(CASE WHEN sm.type = 'IN' THEN sm.quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN sm.type = 'OUT' THEN sm.quantity ELSE 0 END), 0) AS total_out,
        (p.stock * p.price) AS inventory_valuation
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN stock_movements sm ON sm.product_id = p.id
    GROUP BY p.id, p.sku, p.name, p.price, p.stock, p.minimum_stock, c.name
    ORDER BY p.name ASC
");
$productReports = $stmtReportProduct->fetchAll();

// 2. Laporan Ringkasan Kategori dengan HAVING Clause (Slide 14 & 15)
$stmtCategoryReport = $pdo->query("
    SELECT 
        c.name AS category_name,
        COUNT(p.id) AS total_products,
        COALESCE(SUM(p.stock), 0) AS total_stock,
        COALESCE(AVG(p.price), 0) AS average_price,
        COALESCE(SUM(p.stock * p.price), 0) AS category_valuation
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name
    HAVING COUNT(p.id) >= 0
    ORDER BY category_valuation DESC
");
$categoryReports = $stmtCategoryReport->fetchAll();

// 3. Ringkasan Keseluruhan
$totalValuationAll = array_sum(array_column($productReports, 'inventory_valuation'));
$totalStockAll = array_sum(array_column($productReports, 'stock'));
$totalInAll = array_sum(array_column($productReports, 'total_in'));
$totalOutAll = array_sum(array_column($productReports, 'total_out'));

$title = "Laporan Inventory & Agregasi";
require __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2 d-print-none">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Laporan Inventory</h2>
        <p class="text-muted mb-0">Laporan analitik berbasis fungsi agregat SQL (Slide 14, 15, dan 28).</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-dark" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Cetak / Ekspor PDF
        </button>
        <a href="<?= base_url('dashboard.php') ?>" class="btn btn-outline-secondary">Dashboard</a>
    </div>
</div>

<!-- Header Khusus Print -->
<div class="d-none d-print-block mb-4 text-center">
    <h3>INVENTORY MANAGEMENT SYSTEM</h3>
    <h4>Laporan Rekapitulasi Persediaan Barang</h4>
    <p class="text-muted">Dicetak pada: <?= date('d/m/Y H:i') ?> | User: <?= e(current_user()['full_name'] ?? current_user()['username']) ?></p>
    <hr>
</div>

<!-- Kartu Ringkasan Eksekutif -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card shadow-sm border-0 bg-primary bg-opacity-10">
            <div class="card-body">
                <span class="text-muted small fw-semibold text-uppercase">Total Valuasi Aset</span>
                <h4 class="fw-bold text-primary mb-0 mt-1"><?= format_rupiah($totalValuationAll) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm border-0 bg-secondary bg-opacity-10">
            <div class="card-body">
                <span class="text-muted small fw-semibold text-uppercase">Total Fisik Unit</span>
                <h4 class="fw-bold text-dark mb-0 mt-1"><?= number_format($totalStockAll) ?> Unit</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm border-0 bg-success bg-opacity-10">
            <div class="card-body">
                <span class="text-muted small fw-semibold text-uppercase">Total Akumulasi Masuk</span>
                <h4 class="fw-bold text-success mb-0 mt-1">+<?= number_format($totalInAll) ?> Unit</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm border-0 bg-danger bg-opacity-10">
            <div class="card-body">
                <span class="text-muted small fw-semibold text-uppercase">Total Akumulasi Keluar</span>
                <h4 class="fw-bold text-danger mb-0 mt-1">-<?= number_format($totalOutAll) ?> Unit</h4>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Laporan Arus Produk (Slide 28) -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <span class="fw-bold"><i class="bi bi-table text-primary me-2"></i>Laporan Pergerakan & Valuasi per Produk (Slide 28)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>SKU</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th class="text-end">Harga Satuan</th>
                        <th class="text-center text-success">Total IN</th>
                        <th class="text-center text-danger">Total OUT</th>
                        <th class="text-center">Sisa Stok</th>
                        <th class="text-end">Total Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productReports as $idx => $r): ?>
                    <tr>
                        <td class="text-muted small"><?= $idx + 1 ?></td>
                        <td><span class="badge bg-light text-dark font-monospace"><?= e($r['sku']) ?></span></td>
                        <td class="fw-semibold"><?= e($r['name']) ?></td>
                        <td><span class="badge bg-secondary"><?= e($r['category_name']) ?></span></td>
                        <td class="text-end"><?= format_rupiah($r['price']) ?></td>
                        <td class="text-center text-success fw-semibold">+<?= number_format($r['total_in']) ?></td>
                        <td class="text-center text-danger fw-semibold">-<?= number_format($r['total_out']) ?></td>
                        <td class="text-center fw-bold"><?= number_format($r['stock']) ?></td>
                        <td class="text-end fw-bold text-primary"><?= format_rupiah($r['inventory_valuation']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="5" class="text-end">TOTAL KESELURUHAN:</td>
                        <td class="text-center text-success">+<?= number_format($totalInAll) ?></td>
                        <td class="text-center text-danger">-<?= number_format($totalOutAll) ?></td>
                        <td class="text-center"><?= number_format($totalStockAll) ?></td>
                        <td class="text-end text-primary"><?= format_rupiah($totalValuationAll) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Tabel Laporan Agregasi Kategori (Slide 14 & 15) -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <span class="fw-bold"><i class="bi bi-pie-chart text-success me-2"></i>Laporan Agregasi per Kategori (COUNT, SUM, AVG, GROUP BY & HAVING)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Nama Kategori</th>
                        <th class="text-center">Jumlah Jenis Produk (COUNT)</th>
                        <th class="text-center">Total Unit Barang (SUM)</th>
                        <th class="text-end">Rata-rata Harga Satuan (AVG)</th>
                        <th class="text-end">Total Valuasi Kategori</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryReports as $cIdx => $cat): ?>
                    <tr>
                        <td class="text-muted small"><?= $cIdx + 1 ?></td>
                        <td class="fw-bold"><?= e($cat['category_name']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary rounded-pill"><?= number_format($cat['total_products']) ?></span>
                        </td>
                        <td class="text-center fw-semibold"><?= number_format($cat['total_stock']) ?> Unit</td>
                        <td class="text-end text-muted"><?= format_rupiah($cat['average_price']) ?></td>
                        <td class="text-end fw-bold text-success"><?= format_rupiah($cat['category_valuation']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
