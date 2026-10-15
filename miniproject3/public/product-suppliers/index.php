<?php
/**
 * Modul Relasi Produk & Supplier (Many-to-Many Junction Table)
 * Implementasi Slide 8: product_suppliers (product_id, supplier_id, supplier_price)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_login();

$productId = !empty($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$supplierId = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;

// Query dengan JOIN produk dan supplier
$where = [];
$params = [];

if ($productId) {
    $where[] = "ps.product_id = :p_id";
    $params['p_id'] = $productId;
}
if ($supplierId) {
    $where[] = "ps.supplier_id = :s_id";
    $params['s_id'] = $supplierId;
}
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT 
        ps.product_id, ps.supplier_id, ps.supplier_price, ps.created_at,
        p.name AS product_name, p.sku, p.price AS product_price,
        s.name AS supplier_name, s.contact_person, s.phone
    FROM product_suppliers ps
    JOIN products p ON p.id = ps.product_id
    JOIN suppliers s ON s.id = ps.supplier_id
    {$whereSql}
    ORDER BY p.name ASC, s.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$relations = $stmt->fetchAll();

// Ambil list produk & supplier untuk dropdown filter
$productsList = $pdo->query("SELECT id, sku, name FROM products ORDER BY name ASC")->fetchAll();
$suppliersList = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

$title = "Relasi Produk & Supplier (Many-to-Many)";
require __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-link-45deg text-warning me-2"></i>Relasi Produk & Supplier</h2>
        <p class="text-muted mb-0">Implementasi Relasi Many-to-Many melalui junction table <code>product_suppliers</code> (Slide 8).</p>
    </div>
    <?php if (is_admin()): ?>
    <a href="create.php<?= $productId ? '?product_id=' . $productId : '' ?>" class="btn btn-primary d-flex align-items-center">
        <i class="bi bi-plus-circle me-1"></i> Hubungkan Produk & Supplier
    </a>
    <?php endif; ?>
</div>

<!-- Filter Produk / Supplier -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-5">
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
            <div class="col-md-5">
                <label class="form-label small text-muted fw-semibold mb-1">Filter Supplier</label>
                <select name="supplier_id" class="form-select">
                    <option value="">Semua Supplier</option>
                    <?php foreach ($suppliersList as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2 mt-md-4">
                <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                <a href="index.php" class="btn btn-outline-secondary" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold">Daftar Pasangan Produk & Supplier</span>
        <span class="badge bg-secondary"><?= count($relations) ?> Hubungan Terdaftar</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Produk</th>
                        <th>Supplier</th>
                        <th class="text-end">Harga Beli Supplier</th>
                        <th class="text-end">Harga Jual Produk</th>
                        <th class="text-end" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($relations)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            Belum ada relasi many-to-many yang sesuai kriteria.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($relations as $idx => $r): ?>
                        <tr>
                            <td class="text-muted small"><?= $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold">
                                    <a href="<?= base_url('products/detail.php?id=' . $r['product_id']) ?>" class="text-decoration-none text-dark hover-primary">
                                        <?= e($r['product_name']) ?>
                                    </a>
                                </div>
                                <span class="badge bg-light text-dark font-monospace"><?= e($r['sku']) ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($r['supplier_name']) ?></div>
                                <small class="text-muted"><?= e($r['contact_person'] ?: '-') ?> (<?= e($r['phone'] ?: '-') ?>)</small>
                            </td>
                            <td class="text-end fw-bold text-success">
                                <?= $r['supplier_price'] ? format_rupiah($r['supplier_price']) : '-' ?>
                            </td>
                            <td class="text-end text-muted">
                                <?= format_rupiah($r['product_price']) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <?php if (is_admin()): ?>
                                    <a href="edit.php?product_id=<?= $r['product_id'] ?>&supplier_id=<?= $r['supplier_id'] ?>" class="btn btn-outline-primary" title="Ubah Harga Beli">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete.php?product_id=<?= $r['product_id'] ?>&supplier_id=<?= $r['supplier_id'] ?>" class="btn btn-outline-danger" title="Putuskan Relasi" onclick="return confirm('Putuskan hubungan supplier ini dengan produk?');">
                                        <i class="bi bi-link-45deg"></i> Putus
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
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
