<?php
/**
 * Hapus Kategori
 * Menegakkan Integritas Referensial (Slide 9 & 33)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, name FROM categories WHERE id = :id");
$stmt->execute(['id' => $id]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('danger', 'Kategori tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// 1. Cek apakah ada produk yang berelasi dengan kategori ini (FK RESTRICT)
$stmtProd = $pdo->prepare("SELECT COUNT(*) AS total_prod FROM products WHERE category_id = :id");
$stmtProd->execute(['id' => $id]);
$totalProd = (int)$stmtProd->fetch()['total_prod'];

if ($totalProd > 0) {
    set_flash('danger', "Kategori <strong>" . e($category['name']) . "</strong> tidak dapat dihapus karena masih menampung <strong>{$totalProd} produk</strong>. Pindahkan atau hapus produk terlebih dahulu (Integritas Referensial ON DELETE RESTRICT).");
    header('Location: index.php');
    exit;
}

try {
    $stmtDel = $pdo->prepare("DELETE FROM categories WHERE id = :id");
    $stmtDel->execute(['id' => $id]);

    set_flash('success', "Kategori <strong>" . e($category['name']) . "</strong> berhasil dihapus.");
} catch (PDOException $e) {
    set_flash('danger', "Gagal menghapus kategori: " . $e->getMessage());
}

header('Location: index.php');
exit;
