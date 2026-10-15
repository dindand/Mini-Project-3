<?php
/**
 * Hapus Produk
 * Menegakkan Aturan Bisnis & Integritas Referensial (Slide 9 & 33)
 * "Produk yang memiliki histori tidak boleh dihapus sembarangan."
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'ID Produk tidak valid.');
    header('Location: index.php');
    exit;
}

// Ambil info produk
$stmt = $pdo->prepare("SELECT id, name, sku FROM products WHERE id = :id");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// 1. Cek Apakah Memiliki Histori Mutasi Stok (Business Rule Slide 33)
$stmtHist = $pdo->prepare("SELECT COUNT(*) AS total_hist FROM stock_movements WHERE product_id = :id");
$stmtHist->execute(['id' => $id]);
$totalHistory = (int)$stmtHist->fetch()['total_hist'];

if ($totalHistory > 0) {
    set_flash('danger', "Produk <strong>" . e($product['name']) . "</strong> ({$product['sku']}) tidak dapat dihapus karena memiliki <strong>{$totalHistory} data transaksi mutasi stok</strong>. Aturan bisnis dan integritas referensial (ON DELETE RESTRICT) melarang penghapusan produk berhistori untuk mencegah korupsi audit logistik.");
    header('Location: index.php');
    exit;
}

// 2. Jika tidak ada histori, lakukan penghapusan
try {
    $stmtDel = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $stmtDel->execute(['id' => $id]);

    set_flash('success', "Produk <strong>" . e($product['name']) . "</strong> berhasil dihapus.");
} catch (PDOException $e) {
    set_flash('danger', "Gagal menghapus produk karena kendala relasi data: " . $e->getMessage());
}

header('Location: index.php');
exit;
