<?php
/**
 * Modul Kategori - Daftar Kategori dengan Agregat Produk
 * Implementasi Slide 7 (One-to-Many Categories -> Products) & Slide 14 (Aggregate Query)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_login();

// Query Kategori dengan agregat jumlah produk dan total stok (Slide 14)
$stmt = $pdo->query("
    SELECT 
        c.id, c.name, c.description, c.created_at,
        COUNT(p.id) AS total_products,
        COALESCE(SUM(p.stock), 0) AS total_stock
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name, c.description, c.created_at
    ORDER BY c.name ASC
");
$categories = $stmt->fetchAll();

$title = "Kategori Produk";
require __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-tags text-success me-2"></i>Kategori Produk</h2>
        <p class="text-muted mb-0">Kelompokkan barang untuk pengelolaan inventaris yang lebih terstruktur (Relasi One-to-Many).</p>
    </div>
    <?php if (is_admin()): ?>
    <a href="create.php" class="btn btn-primary d-flex align-items-center">
        <i class="bi bi-plus-circle me-1"></i> Tambah Kategori
    </a>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold">Daftar Kategori Tersedia</span>
        <span class="badge bg-secondary"><?= count($categories) ?> Kategori</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Nama Kategori</th>
                        <th>Deskripsi</th>
                        <th class="text-center">Jumlah Produk</th>
                        <th class="text-center">Total Unit Stok</th>
                        <th class="text-end" style="width: 160px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">Belum ada kategori terdaftar.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $idx => $cat): ?>
                        <tr>
                            <td class="text-muted small"><?= $idx + 1 ?></td>
                            <td>
                                <a href="<?= base_url('products/index.php?category_id=' . $cat['id']) ?>" class="fw-bold text-decoration-none text-dark hover-primary">
                                    <?= e($cat['name']) ?>
                                </a>
                            </td>
                            <td class="text-muted small"><?= e($cat['description'] ?: '-') ?></td>
                            <td class="text-center">
                                <a href="<?= base_url('products/index.php?category_id=' . $cat['id']) ?>" class="badge bg-primary text-decoration-none rounded-pill">
                                    <?= number_format($cat['total_products']) ?> Item
                                </a>
                            </td>
                            <td class="text-center fw-semibold">
                                <?= number_format($cat['total_stock']) ?> Unit
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= base_url('products/index.php?category_id=' . $cat['id']) ?>" class="btn btn-outline-info" title="Lihat Produk">
                                        <i class="bi bi-boxes"></i>
                                    </a>
                                    <?php if (is_admin()): ?>
                                    <a href="edit.php?id=<?= $cat['id'] ?>" class="btn btn-outline-primary" title="Edit Kategori">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $cat['id'] ?>" class="btn btn-outline-danger" title="Hapus Kategori" onclick="return confirm('Yakin ingin menghapus kategori ini? Kategori yang memiliki produk tidak dapat dihapus.');">
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
