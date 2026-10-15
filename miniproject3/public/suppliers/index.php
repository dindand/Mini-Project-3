<?php
/**
 * Modul Supplier - Daftar Supplier Pemasok
 * Implementasi Master Data Supplier & Relasi Many-to-Many
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_login();

// Ambil data supplier beserta jumlah produk yang disuplai (Junction table product_suppliers)
$stmt = $pdo->query("
    SELECT 
        s.*,
        COUNT(ps.product_id) AS total_supplied_products
    FROM suppliers s
    LEFT JOIN product_suppliers ps ON ps.supplier_id = s.id
    GROUP BY s.id, s.name, s.contact_person, s.phone, s.email, s.address, s.created_at
    ORDER BY s.name ASC
");
$suppliers = $stmt->fetchAll();

$title = "Data Supplier";
require __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-truck text-info me-2"></i>Data Supplier</h2>
        <p class="text-muted mb-0">Kelola informasi mitra penyedia barang inventaris.</p>
    </div>
    <?php if (is_admin()): ?>
    <a href="create.php" class="btn btn-primary d-flex align-items-center">
        <i class="bi bi-plus-circle me-1"></i> Tambah Supplier
    </a>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold">Daftar Supplier Terdaftar</span>
        <span class="badge bg-secondary"><?= count($suppliers) ?> Supplier</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Nama Supplier</th>
                        <th>Kontak Person</th>
                        <th>Telepon & Email</th>
                        <th>Alamat</th>
                        <th class="text-center">Produk Disuplai</th>
                        <th class="text-end" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Belum ada supplier terdaftar.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $idx => $s): ?>
                        <tr>
                            <td class="text-muted small"><?= $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold"><?= e($s['name']) ?></div>
                            </td>
                            <td><?= e($s['contact_person'] ?: '-') ?></td>
                            <td>
                                <div><i class="bi bi-telephone text-muted me-1"></i><?= e($s['phone'] ?: '-') ?></div>
                                <small class="text-muted"><i class="bi bi-envelope text-muted me-1"></i><?= e($s['email'] ?: '-') ?></small>
                            </td>
                            <td class="text-muted small" style="max-width: 250px;">
                                <?= e($s['address'] ?: '-') ?>
                            </td>
                            <td class="text-center">
                                <a href="<?= base_url('product-suppliers/index.php?supplier_id=' . $s['id']) ?>" class="badge bg-primary text-decoration-none rounded-pill">
                                    <?= number_format($s['total_supplied_products']) ?> Produk
                                </a>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <?php if (is_admin()): ?>
                                    <a href="edit.php?id=<?= $s['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $s['id'] ?>" class="btn btn-outline-danger" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus supplier ini?');">
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
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
