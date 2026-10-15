<?php
/**
 * Hapus Akun Pengguna
 * Menegakkan Integritas Relasi (Slide 9 & 33)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$currentUserId = (int)current_user()['id'];

// 1. Mencegah menghapus akun sendiri
if ($id === $currentUserId) {
    set_flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = :id");
$stmt->execute(['id' => $id]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    set_flash('danger', 'Pengguna tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// 2. Cek apakah user memiliki riwayat transaksi di stock_movements (FK RESTRICT)
$stmtHist = $pdo->prepare("SELECT COUNT(*) AS total_hist FROM stock_movements WHERE user_id = :id");
$stmtHist->execute(['id' => $id]);
$totalHistory = (int)$stmtHist->fetch()['total_hist'];

if ($totalHistory > 0) {
    set_flash('danger', "Pengguna <strong>" . e($targetUser['username']) . "</strong> tidak dapat dihapus karena tercatat sebagai penanggung jawab dalam <strong>{$totalHistory} transaksi mutasi stok</strong>. Demi audit trail dan integritas referensial (ON DELETE RESTRICT), data pengguna berhistori dilindungi.");
    header('Location: index.php');
    exit;
}

// 3. Jika aman, hapus user (user_profiles akan ikut terhapus via CASCADE)
try {
    $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmtDel->execute(['id' => $id]);

    set_flash('success', "Pengguna <strong>" . e($targetUser['username']) . "</strong> berhasil dihapus.");
} catch (PDOException $e) {
    set_flash('danger', "Gagal menghapus pengguna: " . $e->getMessage());
}

header('Location: index.php');
exit;
