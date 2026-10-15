<?php
/**
 * Modul Manajemen Pengguna (Admin Only)
 * Implementasi Slide 24: Basic Authorization (Staff dilarang akses administrasi user)
 * Implementasi Slide 6: One-to-One users <-> user_profiles
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

// Menegakkan Hak Akses Role Admin (Slide 24)
require_role('admin');

$stmt = $pdo->query("
    SELECT 
        u.id, u.username, u.role, u.created_at,
        p.full_name, p.phone, p.address,
        (SELECT COUNT(*) FROM stock_movements sm WHERE sm.user_id = u.id) AS total_transactions
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    ORDER BY u.id ASC
");
$users = $stmt->fetchAll();

$title = "Kelola Pengguna";
require __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-people-fill text-warning me-2"></i>Manajemen Pengguna</h2>
        <p class="text-muted mb-0">Kelola akun otentikasi dan profil pengguna sistem (Relasi One-to-One <code>users &harr; user_profiles</code>).</p>
    </div>
    <a href="create.php" class="btn btn-primary d-flex align-items-center">
        <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna Baru
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold">Daftar Akun Pengguna</span>
        <span class="badge bg-secondary"><?= count($users) ?> Akun Terdaftar</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Username</th>
                        <th>Nama Lengkap (Profil)</th>
                        <th>Peran (Role)</th>
                        <th>Kontak & Alamat</th>
                        <th class="text-center">Total Transaksi</th>
                        <th>Terdaftar Sejak</th>
                        <th class="text-end" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $idx => $u): ?>
                    <tr>
                        <td class="text-muted small"><?= $idx + 1 ?></td>
                        <td>
                            <div class="fw-bold font-monospace"><?= e($u['username']) ?></div>
                            <?php if ($u['id'] === current_user()['id']): ?>
                                <span class="badge bg-light text-primary border">Sesi Anda</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= e($u['full_name'] ?: '-') ?></div>
                        </td>
                        <td>
                            <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-primary' ?> text-uppercase">
                                <?= e($u['role']) ?>
                            </span>
                        </td>
                        <td class="small">
                            <div><i class="bi bi-telephone text-muted me-1"></i><?= e($u['phone'] ?: '-') ?></div>
                            <div class="text-muted"><?= e($u['address'] ?: '-') ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">
                                <?= number_format($u['total_transactions']) ?> Log
                            </span>
                        </td>
                        <td class="small text-muted"><?= format_datetime($u['created_at']) ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-outline-primary" title="Edit Akun">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($u['id'] !== current_user()['id']): ?>
                                <a href="delete.php?id=<?= $u['id'] ?>" class="btn btn-outline-danger" title="Hapus Akun" onclick="return confirm('Apakah Anda yakin ingin menghapus akun ini?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php else: ?>
                                <button class="btn btn-outline-secondary" disabled title="Tidak bisa menghapus akun sendiri">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../views/footer.php'; ?>
