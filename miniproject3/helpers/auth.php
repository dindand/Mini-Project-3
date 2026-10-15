<?php
/**
 * Authentication & Authorization Helpers
 * Implementasi Session, Login, Role, dan Hak Akses sesuai Slide 22, 23, 24
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/escape.php';

/**
 * Cek apakah pengguna saat ini sudah login
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Ambil data user yang sedang login
 */
function current_user(): ?array
{
    return is_logged_in() ? $_SESSION['user'] : null;
}

/**
 * Ambil role user yang sedang login
 */
function current_role(): ?string
{
    return is_logged_in() ? ($_SESSION['user']['role'] ?? null) : null;
}

/**
 * Cek apakah user saat ini memiliki role admin
 */
function is_admin(): bool
{
    return current_role() === 'admin';
}

/**
 * Cek apakah user saat ini memiliki role staff
 */
function is_staff(): bool
{
    return current_role() === 'staff';
}

/**
 * Simpan sesi login user dengan session_regenerate_id(true) untuk mencegah session fixation
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'username'  => $user['username'],
        'role'      => $user['role'],
        'full_name' => $user['full_name'] ?? $user['username'],
    ];
}

/**
 * Logout pengguna dan bersihkan session
 */
function logout_user(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
    }
}

/**
 * Batasi akses hanya untuk pengguna yang sudah login
 */
function require_login(): void
{
    if (!is_logged_in()) {
        if (!function_exists('set_flash')) {
            require_once __DIR__ . '/flash.php';
        }
        set_flash('warning', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

/**
 * Batasi akses berdasarkan Role (misal 'admin')
 * Jika tidak diizinkan, kembalikan HTTP 403 Forbidden sesuai Slide 24
 */
function require_role($allowedRoles): void
{
    require_login();

    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $userRole = current_role();

    if (!in_array($userRole, $roles, true)) {
        http_response_code(403);
        // Tampilkan halaman 403 Forbidden yang rapi
        $title = "403 Akses Ditolak";
        $forbidden_message = "Anda tidak memiliki hak akses untuk membuka halaman administrasi ini. Halaman ini khusus untuk peran: " . implode(', ', $roles) . ".";
        
        // Cek file views/header.php
        $viewsHeader = __DIR__ . '/../views/header.php';
        $viewsFooter = __DIR__ . '/../views/footer.php';
        
        if (file_exists($viewsHeader) && file_exists($viewsFooter)) {
            require $viewsHeader;
            echo '
            <div class="row justify-content-center mt-5">
                <div class="col-md-7 col-lg-6">
                    <div class="card shadow-sm border-danger">
                        <div class="card-header bg-danger text-white d-flex align-items-center">
                            <i class="bi bi-shield-slash-fill fs-4 me-2"></i>
                            <h5 class="mb-0">403 - Akses Ditolak (Forbidden)</h5>
                        </div>
                        <div class="card-body text-center py-4">
                            <div class="display-1 text-danger mb-3"><i class="bi bi-lock-fill"></i></div>
                            <h4 class="card-title text-danger">Otorisasi Gagal</h4>
                            <p class="card-text text-muted">' . e($forbidden_message) . '</p>
                            <p class="small text-secondary">Role Anda saat ini: <strong class="badge bg-secondary">' . e(strtoupper((string)$userRole)) . '</strong></p>
                            <div class="mt-4">
                                <a href="' . base_url('dashboard.php') . '" class="btn btn-primary">
                                    <i class="bi bi-speedometer2 me-1"></i> Kembali ke Dashboard
                                </a>
                                <a href="javascript:history.back()" class="btn btn-outline-secondary ms-2">
                                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Halaman Sebelumnya
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
            require $viewsFooter;
        } else {
            echo "<h1>403 Forbidden</h1><p>" . e($forbidden_message) . "</p>";
        }
        exit;
    }
}
