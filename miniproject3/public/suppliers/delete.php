<?php
/**
 * Hapus Supplier
 * Menegakkan Integritas Relasi (Slide 8 & 33)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, name FROM suppliers WHERE id = :id");
$stmt->execute(['id' => $id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    set_flash('danger', 'Supplier tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// 1. Cek keterkaitan dengan tabel junction product_suppliers (FK RESTRICT)
$stmtPs = $pdo->prepare("SELECT COUNT(*) AS total_links FROM product_suppliers WHERE supplier_id = :id");
$stmtPs->execute(['id' => $id]);
$totalLinks = (int)$stmtPs->fetch()['total_links'];

if ($totalLinks > 0) {
    set_flash('danger', "Supplier <strong>" . e($supplier['name']) . "</strong> tidak dapat dihapus karena masih terhubung dengan <strong>{$totalLinks} data produk</strong> di tabel junction (product_suppliers). Putuskan relasi produk-supplier terlebih dahulu (Integritas Referensial ON DELETE RESTRICT).");
    header('Location: index.php');
    exit;
}

try {
    $stmtDel = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
    $stmtDel->execute(['id' => $id]);

    set_flash('success', "Supplier <strong>" . e($supplier['name']) . "</strong> berhasil dihapus.");
} catch (PDOException $e) {
    set_flash('danger', "Gagal menghapus supplier: " . $e->getMessage());
}

header('Location: index.php');
exit;
