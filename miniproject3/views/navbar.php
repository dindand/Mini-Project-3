<?php
/**
 * Navigation Bar Layout
 * Menampilkan menu sesuai peran (Role-based access view)
 */
$user = current_user();
$role = current_role();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid px-md-4">
        <a class="navbar-brand d-flex align-items-center" href="<?= base_url('dashboard.php') ?>">
            <i class="bi bi-boxes fs-4 text-warning me-2"></i>
            <span>Inventory<span class="text-primary-emphasis fw-bold text-info">System</span></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('dashboard.php') ?>">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                </li>

                <!-- Master Data Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-database me-1"></i> Master Data
                    </a>
                    <ul class="dropdown-menu shadow">
                        <li>
                            <a class="dropdown-item" href="<?= base_url('products/index.php') ?>">
                                <i class="bi bi-box-seam me-2 text-primary"></i> Data Produk
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= base_url('categories/index.php') ?>">
                                <i class="bi bi-tags me-2 text-success"></i> Kategori Produk
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= base_url('suppliers/index.php') ?>">
                                <i class="bi bi-truck me-2 text-info"></i> Data Supplier
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= base_url('product-suppliers/index.php') ?>">
                                <i class="bi bi-link-45deg me-2 text-warning"></i> Relasi Produk - Supplier
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Transaksi Stok Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-arrow-left-right me-1"></i> Transaksi Stok
                    </a>
                    <ul class="dropdown-menu shadow">
                        <li>
                            <a class="dropdown-item" href="<?= base_url('stock/in.php') ?>">
                                <i class="bi bi-arrow-down-circle-fill text-success me-2"></i> Barang Masuk (IN)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= base_url('stock/out.php') ?>">
                                <i class="bi bi-arrow-up-circle-fill text-danger me-2"></i> Barang Keluar (OUT)
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= base_url('stock/history.php') ?>">
                                <i class="bi bi-clock-history text-secondary me-2"></i> Riwayat Mutasi Stok
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Laporan Agregat -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('reports/index.php') ?>">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i> Laporan
                    </a>
                </li>

                <!-- Khusus Role Admin: Manajemen User (Slide 24, 33) -->
                <?php if ($role === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('users/index.php') ?>">
                        <i class="bi bi-people-fill me-1 text-warning"></i> Kelola User
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Sisi Kanan: User Info, Role Badge & Logout -->
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 text-light d-flex align-items-center">
                    <i class="bi bi-person-circle fs-5 me-2"></i>
                    <div class="d-flex flex-column text-end">
                        <span class="fw-semibold small"><?= e($user['full_name'] ?? $user['username']) ?></span>
                        <span class="badge <?= $role === 'admin' ? 'bg-danger' : 'bg-primary' ?> text-uppercase" style="font-size: 0.65rem;">
                            <?= e($role) ?>
                        </span>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('logout.php') ?>" class="btn btn-sm btn-outline-light d-flex align-items-center" onclick="return confirm('Apakah Anda yakin ingin logout?')">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
