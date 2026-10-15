<?php
/**
 * Putuskan Relasi Produk & Supplier
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$productId = (int)($_GET['product_id'] ?? 0);
$supplierId = (int)($_GET['supplier_id'] ?? 0);

if ($productId > 0 && $supplierId > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM product_suppliers WHERE product_id = :p_id AND supplier_id = :s_id");
        $stmt->execute(['p_id' => $productId, 's_id' => $supplierId]);
        set_flash('success', "Relasi produk dengan supplier berhasil diputus dari tabel junction.");
    } catch (PDOException $e) {
        set_flash('danger', "Gagal memutus relasi: " . $e->getMessage());
    }
} else {
    set_flash('danger', "Parameter relasi tidak valid.");
}

header('Location: index.php?product_id=' . $productId);
exit;
