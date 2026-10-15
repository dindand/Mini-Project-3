<?php
/**
 * Dashboard Inventory Management System
 * Sesuai Slide 29 (Widget & Sumber Data Agregat) dan Slide 14 (GROUP BY & Aggregate)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/flash.php';

require_login();

$user = current_user();

// 1. Query Agregat untuk Widget Statistik (Slide 29)
// a. Total Produk
$stmtTotal = $pdo->query("SELECT COUNT(id) AS total_products FROM products");
$totalProducts = (int)$stmtTotal->fetch()['total_products'];

// b. Nilai Inventory (SUM(stock * price))
$stmtValue = $pdo->query("SELECT COALESCE(SUM(stock * price), 0) AS total_inventory_value FROM products");
$totalInventoryValue = (float)$stmtValue->fetch()['total_inventory_value'];

// c. Produk Stok Rendah (stock <= minimum_stock)
$stmtLowStockCount = $pdo->query("SELECT COUNT(id) AS low_stock_count FROM products WHERE stock <= minimum_stock");
$lowStockCount = (int)$stmtLowStockCount->fetch()['low_stock_count'];

// d. Total Barang Masuk (SUM(quantity) WHERE type = 'IN')
$stmtIn = $pdo->query("SELECT COALESCE(SUM(quantity), 0) AS total_in FROM stock_movements WHERE type = 'IN'");
$totalStockIn = (int)$stmtIn->fetch()['total_in'];

// e. Total Barang Keluar (SUM(quantity) WHERE type = 'OUT')
$stmtOut = $pdo->query("SELECT COALESCE(SUM(quantity), 0) AS total_out FROM stock_movements WHERE type = 'OUT'");
$totalStockOut = (int)$stmtOut->fetch()['total_out'];

// 2. Query Agregat Kategori (Slide 14)
$stmtCatAgg = $pdo->query("
    SELECT 
        c.id,
        c.name AS category_name,
        COUNT(p.id) AS total_products,
        COALESCE(SUM(p.stock), 0) AS total_stock,
        COALESCE(AVG(p.price), 0) AS average_price,
        COALESCE(SUM(p.stock * p.price), 0) AS total_category_val
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY total_products DESC
");
$categorySummaries = $stmtCatAgg->fetchAll();

// 3. Query Daftar Produk dengan Stok Rendah (Butuh Restock)
$stmtLowStockList = $pdo->query("
    SELECT p.id, p.sku, p.name, p.stock, p.minimum_stock, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.stock <= p.minimum_stock
    ORDER BY p.stock ASC
");
$lowStockProducts = $stmtLowStockList->fetchAll();

// 4. Riwayat Transaksi Mutasi Stok Terbaru (5 transaksi terakhir)
$stmtRecent = $pdo->query("
    SELECT sm.id, sm.type, sm.quantity, sm.notes, sm.created_at,
           p.name AS product_name, p.sku,
           u.username, prof.full_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN users u ON u.id = sm.user_id
    LEFT JOIN user_profiles prof ON prof.user_id = u.id
    ORDER BY sm.created_at DESC
    LIMIT 6
");
$recentMovements = $stmtRecent->fetchAll();

$title = "Dashboard Utama";
require __DIR__ . '/../views/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h2 class="fw-bold mb-1">Ringkasan Inventory</h2>
        <p class="text-muted mb-0">
            Selamat datang, <strong><?= e($user['full_name'] ?? $user['username']) ?></strong>. Pantau metrik dan arus barang secara real-time.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('stock/in.php') ?>" class="btn btn-success d-flex align-items-center">
            <i class="bi bi-arrow-down-circle-fill me-1"></i> Barang Masuk
        </a>
        <a href="<?= base_url('stock/out.php') ?>" class="btn btn-danger d-flex align-items-center">
            <i class="bi bi-arrow-up-circle-fill me-1"></i> Barang Keluar
        </a>
        <?php if (is_admin()): ?>
        <a href="<?= base_url('products/create.php') ?>" class="btn btn-primary d-flex align-items-center">
            <i class="bi bi-plus-lg me-1"></i> Tambah Produk
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- 5 Widget Metrik Utama (Slide 29) -->
<div class="row g-3 mb-4">
    <!-- Total Produk -->
    <div class="col-sm-6 col-xl-2-4 col-lg-4">
        <div class="card stat-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Produk</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($totalProducts) ?></h3>
                    <small class="text-muted">Item terdaftar</small>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Nilai Inventory -->
    <div class="col-sm-6 col-xl-2-4 col-lg-4">
        <div class="card stat-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Nilai Inventory</span>
                    <h4 class="fw-bold mb-0 mt-1 text-primary"><?= format_rupiah($totalInventoryValue) ?></h4>
                    <small class="text-muted">Valuasi stok total</small>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-currency-dollar"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Produk Stok Rendah -->
    <div class="col-sm-6 col-xl-2-4 col-lg-4">
        <div class="card stat-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Stok Rendah</span>
                    <h3 class="fw-bold mb-0 mt-1 <?= $lowStockCount > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= number_format($lowStockCount) ?>
                    </h3>
                    <small class="text-muted">&le; minimum batas</small>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Barang Masuk -->
    <div class="col-sm-6 col-xl-2-4 col-lg-6">
        <div class="card stat-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Barang Masuk</span>
                    <h3 class="fw-bold mb-0 mt-1 text-success">+<?= number_format($totalStockIn) ?></h3>
                    <small class="text-muted">Total unit masuk (IN)</small>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-box-arrow-in-down"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Barang Keluar -->
    <div class="col-sm-6 col-xl-2-4 col-lg-6">
        <div class="card stat-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Barang Keluar</span>
                    <h3 class="fw-bold mb-0 mt-1 text-danger">-<?= number_format($totalStockOut) ?></h3>
                    <small class="text-muted">Total unit keluar (OUT)</small>
                </div>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-box-arrow-up"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Peringatan Produk Stok Rendah -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center bg-white py-3">
                <span class="d-flex align-items-center fw-bold text-danger">
                    <i class="bi bi-bell-fill text-danger me-2"></i> Peringatan Stok Kritis (&le; Minimum)
                </span>
                <span class="badge bg-danger rounded-pill"><?= count($lowStockProducts) ?> Produk</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lowStockProducts)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check2-circle fs-1 text-success d-block mb-2"></i>
                        Semua stok produk berada di atas batas minimum. Aman!
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Produk</th>
                                    <th>Kategori</th>
                                    <th class="text-center">Sisa Stok</th>
                                    <th class="text-center">Batas Min</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $low): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= e($low['name']) ?></div>
                                        <span class="badge bg-light text-dark font-monospace"><?= e($low['sku']) ?></span>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= e($low['category_name']) ?></span></td>
                                    <td class="text-center">
                                        <span class="badge badge-low-stock fw-bold fs-6">
                                            <?= number_format($low['stock']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center text-muted"><?= number_format($low['minimum_stock']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= base_url('stock/in.php?product_id=' . $low['id']) ?>" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-plus-circle me-1"></i> Restock
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ringkasan Agregat per Kategori (Slide 14) -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center bg-white py-3">
                <span class="fw-bold text-dark">
                    <i class="bi bi-pie-chart-fill text-primary me-2"></i> Ringkasan Kategori (Fungsi Agregat)
                </span>
                <a href="<?= base_url('categories/index.php') ?>" class="small text-decoration-none">Kelola Kategori &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Kategori</th>
                                <th class="text-center">Jml Produk</th>
                                <th class="text-center">Total Unit</th>
                                <th class="text-end">Rata-rata Harga</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categorySummaries as $cat): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($cat['category_name']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary rounded-pill"><?= number_format($cat['total_products']) ?></span>
                                </td>
                                <td class="text-center fw-semibold">
                                    <?= number_format($cat['total_stock']) ?>
                                </td>
                                <td class="text-end text-muted">
                                    <?= format_rupiah($cat['average_price']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mutasi Terakhir (Live Activity Feed) -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center bg-white py-3">
        <span class="fw-bold">
            <i class="bi bi-clock-history text-secondary me-2"></i> Mutasi Stok Terbaru (Live History)
        </span>
        <a href="<?= base_url('stock/history.php') ?>" class="btn btn-sm btn-outline-primary">
            Lihat Semua Riwayat &rarr;
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Waktu</th>
                        <th>Tipe</th>
                        <th>Produk</th>
                        <th>Jumlah</th>
                        <th>Dicatat Oleh</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentMovements)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">Belum ada transaksi mutasi stok.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($recentMovements as $mov): ?>
                        <tr>
                            <td class="text-muted small"><?= format_datetime($mov['created_at']) ?></td>
                            <td>
                                <?php if ($mov['type'] === 'IN'): ?>
                                    <span class="badge badge-in fw-semibold">
                                        <i class="bi bi-arrow-down-left"></i> IN (Masuk)
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-out fw-semibold">
                                        <i class="bi bi-arrow-up-right"></i> OUT (Keluar)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($mov['product_name']) ?></div>
                                <span class="badge bg-light text-dark font-monospace"><?= e($mov['sku']) ?></span>
                            </td>
                            <td>
                                <strong class="<?= $mov['type'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                                    <?= $mov['type'] === 'IN' ? '+' : '-' ?><?= number_format($mov['quantity']) ?>
                                </strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-person me-1"></i><?= e($mov['full_name'] ?? $mov['username']) ?>
                                </span>
                            </td>
                            <td class="text-muted small"><?= e($mov['notes'] ?: '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
