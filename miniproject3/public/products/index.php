<?php
/**
 * Modul Produk - Listing dengan Search, Filter, Sorting, dan Pagination
 * Implementasi Slide 13 (WHERE & LIKE), Slide 16 (Search, Filter, Sorting Allowlist), dan Slide 17 (Pagination)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_login();

// 1. Ambil Parameter Pencarian, Filter, Sorting, Pagination
$search = trim($_GET['search'] ?? '');
$categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$filterStock = trim($_GET['stock_filter'] ?? ''); // 'low' or empty

// Allowlist Sorting (Slide 16)
$allowedSortColumns = [
    'name'  => 'p.name',
    'sku'   => 'p.sku',
    'price' => 'p.price',
    'stock' => 'p.stock',
];
$sort = validate_sort_column($_GET['sort'] ?? null, array_keys($allowedSortColumns), 'name');
$order = validate_sort_direction($_GET['order'] ?? null, 'ASC');
$sqlOrderColumn = $allowedSortColumns[$sort];

// Pagination (Slide 17)
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 6;
$offset = ($page - 1) * $perPage;

// 2. Bangun Query Dinamis dengan Prepared Statement
$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(p.name LIKE :search_name OR p.sku LIKE :search_sku)";
    $params['search_name'] = "%{$search}%";
    $params['search_sku'] = "%{$search}%";
}

if ($categoryId !== null && $categoryId > 0) {
    $whereClauses[] = "p.category_id = :cat_id";
    $params['cat_id'] = $categoryId;
}

if ($filterStock === 'low') {
    $whereClauses[] = "p.stock <= p.minimum_stock";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Hitung Total Data (untuk pagination ceiling)
$countSql = "SELECT COUNT(p.id) AS total FROM products p {$whereSql}";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalData = (int)$stmtCount->fetch()['total'];
$totalPage = max(1, (int)ceil($totalData / $perPage));
if ($page > $totalPage && $totalData > 0) {
    $page = $totalPage;
    $offset = ($page - 1) * $perPage;
}

// Ambil Data Produk (Slide 12: JOIN categories)
$dataSql = "
    SELECT 
        p.id, p.category_id, p.sku, p.name, p.price, p.stock, p.minimum_stock,
        c.name AS category_name,
        (SELECT COUNT(ps.supplier_id) FROM product_suppliers ps WHERE ps.product_id = p.id) AS supplier_count
    FROM products p
    JOIN categories c ON c.id = p.category_id
    {$whereSql}
    ORDER BY {$sqlOrderColumn} {$order}
    LIMIT :limit OFFSET :offset
";

$stmtData = $pdo->prepare($dataSql);
foreach ($params as $key => $val) {
    $stmtData->bindValue(":{$key}", $val);
}
$stmtData->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtData->execute();
$products = $stmtData->fetchAll();

// Ambil daftar kategori untuk dropdown filter
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// Helper untuk mempertahankan parameter URL saat paginasi & sorting
function build_query_url(array $overrides = []): string
{
    $current = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($current[$k]);
        } else {
            $current[$k] = $v;
        }
    }
    return '?' . http_build_query($current);
}

$title = "Katalog Produk";
require __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-box-seam text-primary me-2"></i>Katalog Produk</h2>
        <p class="text-muted mb-0">Kelola informasi produk, stok minimum, dan keterkaitan kategori.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (is_admin()): ?>
        <a href="<?= base_url('products/create.php') ?>" class="btn btn-primary d-flex align-items-center">
            <i class="bi bi-plus-circle me-1"></i> Tambah Produk Baru
        </a>
        <?php endif; ?>
        <a href="<?= base_url('reports/index.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Laporan
        </a>
    </div>
</div>

<!-- Form Pencarian, Filter & Sorting (Slide 16) -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <!-- Search SKU / Nama -->
            <div class="col-md-4 col-12">
                <label class="form-label small text-muted fw-semibold mb-1">Cari Nama / SKU</label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Contoh: ThinkPad / PRD-..." value="<?= e($search) ?>">
                </div>
            </div>

            <!-- Filter Kategori -->
            <div class="col-md-3 col-sm-6">
                <label class="form-label small text-muted fw-semibold mb-1">Kategori</label>
                <select name="category_id" class="form-select">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Stok -->
            <div class="col-md-2 col-sm-6">
                <label class="form-label small text-muted fw-semibold mb-1">Status Stok</label>
                <select name="stock_filter" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="low" <?= $filterStock === 'low' ? 'selected' : '' ?>>Stok Kritis (&le; Min)</option>
                </select>
            </div>

            <!-- Tombol Aksi Filter -->
            <div class="col-md-3 col-12 d-flex gap-2 mt-md-4">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-filter me-1"></i> Terapkan
                </button>
                <a href="index.php" class="btn btn-outline-secondary" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
            
            <!-- Hidden field for current sorting -->
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="order" value="<?= e($order) ?>">
        </form>
    </div>
</div>

<!-- Tabel Data Produk -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-bold">Daftar Produk</span>
            <span class="badge bg-secondary ms-2"><?= number_format($totalData) ?> Produk Ditemukan</span>
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
                        <th>
                            <a href="<?= build_query_url(['sort' => 'sku', 'order' => ($sort === 'sku' && $order === 'ASC') ? 'DESC' : 'ASC']) ?>" class="text-dark text-decoration-none d-flex align-items-center">
                                SKU
                                <?php if ($sort === 'sku'): ?>
                                    <i class="bi bi-arrow-<?= $order === 'ASC' ? 'up' : 'down' ?> ms-1 text-primary"></i>
                                <?php else: ?>
                                    <i class="bi bi-arrow-down-up ms-1 text-muted opacity-50"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= build_query_url(['sort' => 'name', 'order' => ($sort === 'name' && $order === 'ASC') ? 'DESC' : 'ASC']) ?>" class="text-dark text-decoration-none d-flex align-items-center">
                                Nama Produk
                                <?php if ($sort === 'name'): ?>
                                    <i class="bi bi-arrow-<?= $order === 'ASC' ? 'up' : 'down' ?> ms-1 text-primary"></i>
                                <?php else: ?>
                                    <i class="bi bi-arrow-down-up ms-1 text-muted opacity-50"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Kategori</th>
                        <th>
                            <a href="<?= build_query_url(['sort' => 'price', 'order' => ($sort === 'price' && $order === 'ASC') ? 'DESC' : 'ASC']) ?>" class="text-dark text-decoration-none d-flex align-items-center">
                                Harga
                                <?php if ($sort === 'price'): ?>
                                    <i class="bi bi-arrow-<?= $order === 'ASC' ? 'up' : 'down' ?> ms-1 text-primary"></i>
                                <?php else: ?>
                                    <i class="bi bi-arrow-down-up ms-1 text-muted opacity-50"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="text-center">
                            <a href="<?= build_query_url(['sort' => 'stock', 'order' => ($sort === 'stock' && $order === 'ASC') ? 'DESC' : 'ASC']) ?>" class="text-dark text-decoration-none d-inline-flex align-items-center">
                                Sisa Stok
                                <?php if ($sort === 'stock'): ?>
                                    <i class="bi bi-arrow-<?= $order === 'ASC' ? 'up' : 'down' ?> ms-1 text-primary"></i>
                                <?php else: ?>
                                    <i class="bi bi-arrow-down-up ms-1 text-muted opacity-50"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="text-center">Min. Stok</th>
                        <th class="text-center">Supplier</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                            Tidak ada produk yang sesuai dengan kriteria pencarian atau filter.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($products as $idx => $p): 
                            $isLow = (int)$p['stock'] <= (int)$p['minimum_stock'];
                        ?>
                        <tr>
                            <td class="text-muted small"><?= $offset + $idx + 1 ?></td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace"><?= e($p['sku']) ?></span>
                            </td>
                            <td>
                                <a href="detail.php?id=<?= $p['id'] ?>" class="fw-semibold text-decoration-none text-dark hover-primary">
                                    <?= e($p['name']) ?>
                                </a>
                                <?php if ($isLow): ?>
                                    <span class="badge bg-danger ms-1" title="Stok berada pada atau di bawah batas minimum!">
                                        <i class="bi bi-exclamation-triangle-fill"></i> Kritis
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= e($p['category_name']) ?></span>
                            </td>
                            <td class="fw-semibold text-primary">
                                <?= format_rupiah($p['price']) ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $isLow ? 'badge-low-stock text-danger fw-bold' : 'bg-light text-dark border' ?> px-2 py-1">
                                    <?= number_format($p['stock']) ?>
                                </span>
                            </td>
                            <td class="text-center text-muted small">
                                <?= number_format($p['minimum_stock']) ?>
                            </td>
                            <td class="text-center">
                                <a href="<?= base_url('product-suppliers/index.php?product_id=' . $p['id']) ?>" class="badge bg-info text-dark text-decoration-none">
                                    <?= number_format($p['supplier_count']) ?> Supplier
                                </a>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="detail.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary" title="Detail & Riwayat">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= base_url('stock/in.php?product_id=' . $p['id']) ?>" class="btn btn-outline-success" title="Barang Masuk">
                                        <i class="bi bi-plus-lg"></i>
                                    </a>
                                    <a href="<?= base_url('stock/out.php?product_id=' . $p['id']) ?>" class="btn btn-outline-warning" title="Barang Keluar">
                                        <i class="bi bi-dash-lg"></i>
                                    </a>
                                    <?php if (is_admin()): ?>
                                    <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Edit Produk">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-outline-danger" title="Hapus Produk" onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Navigasi (Slide 17) -->
    <?php if ($totalPage > 1): ?>
    <div class="card-footer bg-white py-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
        <span class="small text-muted">
            Menampilkan <?= min($offset + 1, $totalData) ?> sampai <?= min($offset + $perPage, $totalData) ?> dari <?= $totalData ?> item
        </span>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0">
                <!-- Tombol Prev -->
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= build_query_url(['page' => $page - 1]) ?>" aria-label="Previous">
                        &laquo;
                    </a>
                </li>

                <?php for ($i = 1; $i <= $totalPage; $i++): ?>
                    <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= build_query_url(['page' => $i]) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <!-- Tombol Next -->
                <li class="page-item <?= ($page >= $totalPage) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= build_query_url(['page' => $page + 1]) ?>" aria-label="Next">
                        &raquo;
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
