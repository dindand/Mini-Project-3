<?php
/**
 * Halaman 403 Forbidden
 * Slide 24: Basic Authorization (Staff tidak boleh akses admin)
 */

require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/auth.php';

http_response_code(403);
$title = "403 Akses Ditolak";
require __DIR__ . '/../views/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-7 col-lg-6">
        <div class="card shadow border-danger">
            <div class="card-header bg-danger text-white d-flex align-items-center">
                <i class="bi bi-shield-slash-fill fs-4 me-2"></i>
                <h5 class="mb-0">403 - Akses Ditolak (Forbidden)</h5>
            </div>
            <div class="card-body text-center py-5">
                <div class="display-1 text-danger mb-3"><i class="bi bi-lock-fill"></i></div>
                <h3 class="card-title text-danger fw-bold">Otorisasi Gagal</h3>
                <p class="card-text text-muted">
                    Anda tidak memiliki izin akses untuk membuka halaman ini. Sesuai <em>Business Rule</em> (Slide 24 & 33), hak akses modul administratif dibatasi khusus bagi pengguna dengan peran tertentu.
                </p>
                <?php if (is_logged_in()): ?>
                    <p class="small text-secondary">
                        Role Anda saat ini: <strong class="badge bg-secondary"><?= e(strtoupper(current_role())) ?></strong>
                    </p>
                <?php endif; ?>
                <div class="mt-4">
                    <a href="<?= base_url('dashboard.php') ?>" class="btn btn-primary px-4">
                        <i class="bi bi-speedometer2 me-1"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
