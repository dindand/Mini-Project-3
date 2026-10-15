<?php
/**
 * Riwayat Mutasi Stok (Stock Movements Audit Trail)
 * Menampilkan pencatatan arus barang masuk dan keluar dengan filter dan pagination
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_login();

// 1. Parameter Filter & Pagination
$type = trim($_GET['type'] ?? '');
$productId = !empty($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// 2. Bangun Query Dinamis
$where = [];
$params = [];

if (in_array($type, ['IN', 'OUT'], true)) {
    $where[] = "sm.type = :type";
    $params['type'] = $type;
}

if ($productId) {
    $where[] = "sm.product_id = :p_id";
    $params['p_id'] = $productId;
}

if ($dateFrom !== '') {
    $where[] = "DATE(sm.created_at) >= :d_from";
    $params['d_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "DATE(sm.created_at) <= :d_to";
    $params['d_to'] = $dateTo;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Hitung Total Data
$countSql = "SELECT COUNT(sm.id) AS total FROM stock_movements sm {$whereSql}";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalData = (int)$stmtCount->fetch()['total'];
$totalPage = max(1, (int)ceil($totalData / $perPage));

// Ambil Data Mutasi (JOIN produk, users, user_profiles)
$dataSql = "
    SELECT 
        sm.id, sm.type, sm.quantity, sm.notes, sm.created_at,
        p.id AS product_id, p.name AS product_name, p.sku,
        u.username, u.role AS user_role,
        prof.full_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN users u ON u.id = sm.user_id
    LEFT JOIN user_profiles prof ON prof.user_id = u.id
    {$whereSql}
    ORDER BY sm.created_at DESC, sm.id DESC
    LIMIT :limit OFFSET :offset
";

$stmtData = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
    $stmtData->bindValue(":{$k}", $v);
}
$stmtData->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtData->execute();
$movements = $stmtData->fetchAll();

// Daftar produk untuk filter dropdown
$productsList = $pdo->query("SELECT id, sku, name FROM products ORDER BY name ASC")->fetchAll();

// Helper URL Paginasi
function history_url(array $overrides = []): string
{
    $current = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($current[$k]);
        else $current[$k] = $v;
    }
    return '?' . http_build_query($current);
}

$title = "Riwayat Mutasi Stok";
require __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-clock-history text-secondary me-2"></i>Riwayat Mutasi Stok</h2>
        <p class="text-muted mb-0">Catatan lengkap setiap arus masuk dan keluar barang untuk keperluan pelacakan dan audit.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="in.php" class="btn btn-success d-flex align-items-center">
            <i class="bi bi-plus-circle me-1"></i> Barang Masuk
        </a>
        <a href="out.php" class="btn btn-danger d-flex align-items-center">
            <i class="bi bi-dash-circle me-1"></i> Barang Keluar
        </a>
    </div>
</div>

<!-- Filter Pencarian -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="history.php" class="row g-2 align-items-center">
            <div class="col-md-2 col-sm-6">
                <label class="form-label small text-muted fw-semibold mb-1">Tipe Mutasi</label>
                <select name="type" class="form-select">
                    <option value="">Semua Tipe</option>
                    <option value="IN" <?= $type === 'IN' ? 'selected' : '' ?>>Barang Masuk (IN)</option>
                    <option value="OUT" <?= $type === 'OUT' ? 'selected' : '' ?>>Barang Keluar (OUT)</option>
                </select>
            </div>

            <div class="col-md-4 col-sm-6">
                <label class="form-label small text-muted fw-semibold mb-1">Filter Produk</label>
                <select name="product_id" class="form-select">
                    <option value="">Semua Produk</option>
                    <?php foreach ($productsList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>>
                            [<?= e($p['sku']) ?>] <?= e($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-sm-6">
                <label class="form-label small text-muted fw-semibold mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
            </div>

            <div class="col-md-2 col-sm-6">
                <label class="form-label small text-muted fw-semibold mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
            </div>

            <div class="col-md-2 col-12 d-flex gap-2 mt-md-4">
                <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                <a href="history.php" class="btn btn-outline-secondary" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Histori Mutasi -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-bold">Catatan Log Mutasi</span>
            <span class="badge bg-secondary ms-2"><?= number_format($totalData) ?> Transaksi</span>
        </div>
        <div class="small text-muted">
            Halaman <?= $page ?> dari <?= $totalPage ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>#</th>
                        <th>Waktu Transaksi</th>
                        <th>Tipe</th>
                        <th>Produk</th>
                        <th class="text-center">Jumlah Unit</th>
                        <th>User Pelaksana</th>
                        <th>Catatan / Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-clock-history fs-1 d-block mb-2 text-secondary"></i>
                            Tidak ada data mutasi yang sesuai kriteria filter.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($movements as $idx => $m): ?>
                        <tr>
                            <td class="text-muted small"><?= $offset + $idx + 1 ?></td>
                            <td class="small text-muted">
                                <i class="bi bi-calendar3 me-1"></i><?= format_datetime($m['created_at']) ?>
                            </td>
                            <td>
                                <?php if ($m['type'] === 'IN'): ?>
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
                                <a href="<?= base_url('products/detail.php?id=' . $m['product_id']) ?>" class="fw-semibold text-decoration-none text-dark hover-primary">
                                    <?= e($m['product_name']) ?>
                                </a>
                                <span class="badge bg-light text-dark font-monospace ms-1"><?= e($m['sku']) ?></span>
                            </td>
                            <td class="text-center">
                                <span class="fs-6 fw-bold <?= $m['type'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                                    <?= $m['type'] === 'IN' ? '+' : '-' ?><?= number_format($m['quantity']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?= e($m['full_name'] ?? $m['username']) ?></div>
                                <span class="badge <?= $m['user_role'] === 'admin' ? 'bg-danger' : 'bg-primary' ?> text-uppercase" style="font-size: 0.65rem;">
                                    <?= e($m['user_role']) ?>
                                </span>
                            </td>
                            <td class="text-muted small" style="max-width: 300px;">
                                <?= e($m['notes'] ?: '-') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginasi -->
    <?php if ($totalPage > 1): ?>
    <div class="card-footer bg-white py-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
        <span class="small text-muted">
            Menampilkan <?= min($offset + 1, $totalData) ?> sampai <?= min($offset + $perPage, $totalData) ?> dari <?= $totalData ?> baris
        </span>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= history_url(['page' => $page - 1]) ?>">&laquo;</a>
                </li>
                <?php for ($i = 1; $i <= $totalPage; $i++): ?>
                    <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= history_url(['page' => $i]) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $totalPage) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= history_url(['page' => $page + 1]) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
