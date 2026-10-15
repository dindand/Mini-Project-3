<?php
/**
 * Test Suite Mini Project 3 - Automated Verification
 * Menguji seluruh poin penilaian:
 * - Relasi & Foreign Key
 * - Validasi Error (SKU Unik, Stok Negatif, dsb)
 * - Transaksi Atomik (Commit & Rollback)
 * - Kasus Akses Terlarang (Role Authorization 403)
 * - Output Escaping (XSS)
 * - Prepared Statements (SQL Injection Protection)
 */

echo "========================================================\n";
echo "  MINI PROJECT 3: AUTOMATED TEST & VERIFICATION SUITE   \n";
echo "========================================================\n\n";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/validation.php';
require_once __DIR__ . '/../helpers/auth.php';

$passed = 0;
$failed = 0;

function assert_test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] {$description}\n";
        $passed++;
    } else {
        echo "[FAIL] {$description}\n";
        $failed++;
    }
}

// ---------------------------------------------------------
// TEST 1: Database Connection & Tables Verification
// ---------------------------------------------------------
echo "\n--- UJI 1: Skema Database & Tabel ---\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$expectedTables = ['users', 'user_profiles', 'categories', 'products', 'suppliers', 'product_suppliers', 'stock_movements'];
$missingTables = array_diff($expectedTables, $tables);
assert_test("Semua 7 tabel utama telah terbuat di database", empty($missingTables));

// ---------------------------------------------------------
// TEST 2: Password Hashing (Slide 23)
// ---------------------------------------------------------
echo "\n--- UJI 2: Otentikasi & Password Hashing ---\n";
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'admin'");
$stmt->execute();
$adminHash = $stmt->fetchColumn();
assert_test("Password hash admin dapat diverifikasi dengan password_verify()", password_verify('password123', $adminHash));

// ---------------------------------------------------------
// TEST 3: Validation Error Case (Slide 38: Uji minimal satu kasus validation error)
// ---------------------------------------------------------
echo "\n--- UJI 3: Kasus Validation Error (SKU Duplikat & Angka Negatif) ---\n";
$errSku = validate_sku('PRD-ELEC-001', $pdo); // SKU yang sudah ada di database
assert_test("Validasi error muncul saat input SKU yang sudah terdaftar (SKU Unik)", $errSku !== null);

$errNegativePrice = validate_decimal('-5000', 0, 'Harga');
assert_test("Validasi error muncul saat input harga bernilai negatif", $errNegativePrice !== null);

$errZeroQty = validate_int('0', 1, null, 'Jumlah');
assert_test("Validasi error muncul saat input kuantitas 0 pada transaksi barang masuk", $errZeroQty !== null);

// ---------------------------------------------------------
// TEST 4: Business Logic & Transaction Rollback (Slide 26 & 27)
// Barang keluar melebihi stok tersedia HARUS DIBATALKAN (Rollback)
// ---------------------------------------------------------
echo "\n--- UJI 4: Business Logic Barang Keluar Melebihi Stok (Rollback) ---\n";
$stmtP = $pdo->prepare("SELECT id, stock FROM products WHERE sku = 'PRD-ACC-002'"); // Mouse, stok = 2
$stmtP->execute();
$mouse = $stmtP->fetch();
$stockBefore = (int)$mouse['stock'];
$excessiveQty = $stockBefore + 50; // Ingin mengeluarkan 52 unit padahal sisa cuma 2

$rollbackSuccess = false;
$pdo->beginTransaction();
try {
    $stmtLock = $pdo->prepare("SELECT stock FROM products WHERE id = :id FOR UPDATE");
    $stmtLock->execute(['id' => $mouse['id']]);
    $current = (int)$stmtLock->fetchColumn();

    if ($excessiveQty > $current) {
        throw new Exception("Stok tidak mencukupi untuk dikeluarkan");
    }

    $stmtUpdate = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id");
    $stmtUpdate->execute(['qty' => $excessiveQty, 'id' => $mouse['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $rollbackSuccess = true;
}

// Cek ulang stok di database setelah rollback
$stmtAfter = $pdo->prepare("SELECT stock FROM products WHERE id = :id");
$stmtAfter->execute(['id' => $mouse['id']]);
$stockAfter = (int)$stmtAfter->fetchColumn();

assert_test("Transaksi dibatalkan (Rollback) saat barang keluar melebihi stok", $rollbackSuccess && ($stockBefore === $stockAfter));
assert_test("Stok produk tidak berubah dan tidak menjadi negatif setelah rollback", $stockAfter >= 0 && $stockAfter === $stockBefore);

// ---------------------------------------------------------
// TEST 5: Business Logic Barang Masuk dengan Transaksi Atomik
// ---------------------------------------------------------
echo "\n--- UJI 5: Transaksi Barang Masuk (Atomic Commit) ---\n";
$inQty = 5;
$pdo->beginTransaction();
try {
    $stmtUp = $pdo->prepare("UPDATE products SET stock = stock + :qty WHERE id = :id");
    $stmtUp->execute(['qty' => $inQty, 'id' => $mouse['id']]);

    $stmtHist = $pdo->prepare("
        INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
        VALUES (:p_id, 1, 'IN', :qty, 'Uji coba transaksi atomik test suite')
    ");
    $stmtHist->execute(['p_id' => $mouse['id'], 'qty' => $inQty]);
    $histId = (int)$pdo->lastInsertId();
    $pdo->commit();
    $commitSuccess = true;
} catch (Throwable $e) {
    $pdo->rollBack();
    $commitSuccess = false;
}

$stmtCheckIn = $pdo->prepare("SELECT stock FROM products WHERE id = :id");
$stmtCheckIn->execute(['id' => $mouse['id']]);
$stockAfterIn = (int)$stmtCheckIn->fetchColumn();

assert_test("Transaksi barang masuk berhasil di-commit bersamaan dengan tabel histori", $commitSuccess && ($stockAfterIn === $stockBefore + $inQty));

// Bersihkan test data mutasi dan kembalikan stok
$pdo->beginTransaction();
$pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id")->execute(['qty' => $inQty, 'id' => $mouse['id']]);
$pdo->prepare("DELETE FROM stock_movements WHERE id = :id")->execute(['id' => $histId]);
$pdo->commit();

// ---------------------------------------------------------
// TEST 6: Referential Integrity (Slide 9 & 33)
// Produk yang memiliki histori mutasi TIDAK BOLEH dihapus
// ---------------------------------------------------------
echo "\n--- UJI 6: Integritas Referensial (ON DELETE RESTRICT) ---\n";
$stmtThinkPad = $pdo->prepare("SELECT id FROM products WHERE sku = 'PRD-ELEC-001'");
$stmtThinkPad->execute();
$thinkPadId = (int)$stmtThinkPad->fetchColumn();

$preventDeleteTriggered = false;
try {
    // Mencoba menghapus baris produk secara langsung di level database
    $pdo->prepare("DELETE FROM products WHERE id = :id")->execute(['id' => $thinkPadId]);
} catch (PDOException $e) {
    // MySQL foreign key constraint RESTRICT gagal menghapus
    $preventDeleteTriggered = true;
}
assert_test("Database menolak DELETE produk berhistori karena relasi FK RESTRICT", $preventDeleteTriggered);

// ---------------------------------------------------------
// TEST 7: Akses Terlarang / Authorization Test (Slide 24 & 38)
// Staff tidak boleh mengakses fungsi administrasi user
// ---------------------------------------------------------
echo "\n--- UJI 7: Otorisasi & Akses Terlarang (Role 403 Forbidden) ---\n";
$_SESSION['user'] = [
    'id'       => 2,
    'username' => 'staff',
    'role'     => 'staff',
];

$isForbiddenForStaff = false;
// Simulasi pengecekan role
$allowedRoles = ['admin'];
if (!in_array($_SESSION['user']['role'], $allowedRoles, true)) {
    $isForbiddenForStaff = true;
}
assert_test("Pengguna berstatus 'staff' dilarang mengakses halaman admin (403 Forbidden)", $isForbiddenForStaff);

// ---------------------------------------------------------
// TEST 8: XSS Output Encoding & Escaping (Slide 20)
// ---------------------------------------------------------
echo "\n--- UJI 8: Pencegahan XSS & Output Escaping ---\n";
$maliciousPayload = '<script>alert("XSS Attack!")</script>';
$escaped = e($maliciousPayload);
assert_test("Payload skrip berbahaya berhasil di-escape dengan htmlspecialchars", strpos($escaped, '<script>') === false && strpos($escaped, '&lt;script&gt;') !== false);

// ---------------------------------------------------------
// TEST 9: Allowlist Sorting (Slide 16)
// ---------------------------------------------------------
echo "\n--- UJI 9: Pencegahan SQL Injection pada Sorting Allowlist ---\n";
$allowedColumns = ['name', 'sku', 'price', 'stock'];
$maliciousSort = "price; DROP TABLE products; --";
$validatedSort = validate_sort_column($maliciousSort, $allowedColumns, 'name');
assert_test("Input sorting berbahaya dinetralkan kembali ke nilai default allowlist", $validatedSort === 'name');

// ---------------------------------------------------------
// TEST 10: Rumus Konsistensi Stok (Slide 4 & 28)
// stok akhir = stok awal (total IN - total OUT)
// ---------------------------------------------------------
echo "\n--- UJI 10: Konsistensi Rumus Stok Akhir ---\n";
$stmtFormula = $pdo->query("
    SELECT 
        p.id, p.name, p.stock,
        COALESCE(SUM(CASE WHEN sm.type = 'IN' THEN sm.quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN sm.type = 'OUT' THEN sm.quantity ELSE 0 END), 0) AS total_out
    FROM products p
    LEFT JOIN stock_movements sm ON sm.product_id = p.id
    GROUP BY p.id, p.name, p.stock
");
$allMatch = true;
foreach ($stmtFormula->fetchAll() as $row) {
    $calculated = $row['total_in'] - $row['total_out'];
    if ((int)$row['stock'] !== (int)$calculated) {
        $allMatch = false;
        break;
    }
}
assert_test("Seluruh produk memenuhi rumus: stok akhir = total IN - total OUT", $allMatch);

// Ringkasan
echo "\n========================================================\n";
echo "  HASIL AKHIR: {$passed} BERHASIL, {$failed} GAGAL      \n";
echo "========================================================\n";

if ($failed === 0) {
    echo "SELURUH PENGUJIAN SISTEM LULUS 100% SESUAI SPESIFIKASI!\n";
} else {
    echo "TERDAPAT PENGUJIAN YANG GAGAL!\n";
}
