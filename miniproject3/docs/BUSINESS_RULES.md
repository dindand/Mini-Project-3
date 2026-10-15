# Dokumentasi Aturan Bisnis (Business Rules)

**Mini Project 3 — Inventory Management System**  
Mata Kuliah / Materi: *Pertemuan 4: Relational Database + Advanced PHP*

Aplikasi ini mengimplementasikan seluruh aturan bisnis (*business rules*) yang diwajibkan pada Slide 33:

---

### 1. SKU Harus Unik (*Unique SKU*)
- **Deskripsi**: Setiap produk memiliki Stock Keeping Unit (SKU) yang unik sebagai identifikasi universal di pergudangan.
- **Implementasi Database**: Kolom `products.sku` memiliki *constraint* `UNIQUE`.
- **Implementasi PHP**: Fungsi `validate_sku($sku, $pdo, $excludeId)` pada [`helpers/validation.php`](file:///c:/Users/ASUS/Documents/miniproject3/helpers/validation.php) mengecek keberadaan SKU di database sebelum proses `INSERT` atau `UPDATE`. Jika SKU sudah ada, sistem mengembalikan pesan error validasi semantik:
  > *"SKU sudah digunakan oleh produk lain. SKU harus unik."*

---

### 2. Harga dan Quantity Tidak Boleh Negatif
- **Deskripsi**: Nilai nominal harga produk, harga pasokan supplier, serta kuantitas mutasi barang tidak boleh bernilai negatif atau nol pada proses transaksi.
- **Implementasi PHP**:
  - `validate_decimal($price, 0.0, 'Harga Produk')`: Memastikan nilai desimal/angka $\ge 0$.
  - `validate_int($qty, 1, 1000000, 'Jumlah Barang')`: Memastikan jumlah barang masuk maupun barang keluar adalah bilangan bulat positif ($\ge 1$).

---

### 3. Barang Keluar Tidak Boleh Melebihi Stok & Stok Tidak Boleh Negatif
- **Deskripsi**: Sistem melarang pengeluaran barang dalam jumlah yang lebih besar dari sisa stok fisik yang tersedia di database.
- **Rumus Konsistensi Stok**:
  $$\text{stok akhir} = \text{stok awal} + \text{total barang masuk} - \text{total barang keluar}$$
- **Implementasi Database Transaction (ACID)** pada [`public/stock/out.php`](file:///c:/Users/ASUS/Documents/miniproject3/public/stock/out.php):
  1. Memulai transaksi: `$pdo->beginTransaction()`.
  2. Kunci baris (*pessimistic row locking*): `SELECT stock FROM products WHERE id = :id FOR UPDATE`.
  3. Cek validasi logika: `if ($quantity > $stock) throw new Exception(...)`.
  4. Jika valid, kurangi stok dan catat histori ke `stock_movements (type = 'OUT')`.
  5. `$pdo->commit()`.
  6. Jika terjadi kegagalan atau stok kurang, `$pdo->rollBack()` dieksekusi secara otomatis, menjaga saldo stok tidak berubah dan tidak pernah bernilai negatif.

---

### 4. Produk yang Memiliki Histori Tidak Boleh Dihapus Sembarangan
- **Deskripsi**: Produk yang sudah pernah memiliki pergerakan mutasi (baik barang masuk maupun barang keluar) tidak boleh dihapus demi mempertahankan keabsahan riwayat audit logistik dan laporan keuangan.
- **Implementasi Relasional**:
  - Tabel `stock_movements` memasang `FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT`.
- **Implementasi PHP** pada [`public/products/delete.php`](file:///c:/Users/ASUS/Documents/miniproject3/public/products/delete.php):
  - Sistem melakukan pra-pemeriksaan: `SELECT COUNT(*) FROM stock_movements WHERE product_id = :id`.
  - Jika $\text{count} > 0$, sistem langsung membatalkan proses penghapusan dan memunculkan notifikasi bahaya yang informatif kepada admin.

---

### 5. Halaman Internal Membutuhkan Login (*Authentication Guard*)
- **Deskripsi**: Seluruh fungsionalitas internal (dashboard, katalog produk, stok, kategori, supplier, laporan, user) wajib dilindungi autentikasi sesi. Pengunjung anonim (*guest*) hanya diizinkan melihat halaman masuk (*login*).
- **Implementasi PHP**: Fungsi `require_login()` pada [`helpers/auth.php`](file:///c:/Users/ASUS/Documents/miniproject3/helpers/auth.php) mengecek apakah `$_SESSION['user']` valid. Jika belum login, request akan langsung dialihkan (*redirect*) ke `login.php`.

---

### 6. Staff Tidak Boleh Mengakses Administrasi User (*Basic Authorization*)
- **Deskripsi**: Pembagian hak akses (*Role-Based Access Control* / RBAC):
  - **Admin**: Mengelola pengguna, kategori, produk, supplier, relasi many-to-many, mutasi stok, dan laporan.
  - **Staff**: Hanya dapat melihat katalog produk, mencatat transaksi barang masuk (IN), barang keluar (OUT), melihat riwayat mutasi, dan dashboard ringkasan. Staff **DILARANG KERAS** membuka menu kelola pengguna.
- **Implementasi Server-Side**:
  - Pada [`public/users/index.php`](file:///c:/Users/ASUS/Documents/miniproject3/public/users/index.php) dipasang `require_role('admin')`.
  - Jika pengguna dengan role `staff` memanggil URL tersebut secara langsung, server menetapkan `http_response_code(403)` dan merender halaman khusus **403 Forbidden - Akses Ditolak**.

---

### 7. Sorting Hanya Menggunakan Kolom yang Diizinkan (*Allowlist Sorting*)
- **Deskripsi**: Parameter pengurutan kolom SQL `ORDER BY` tidak dapat dipasangi PDO parameter binding biasa (`:param`), sehingga rentan terhadap serangan SQL Injection bila langsung digabungkan dari input URL `$_GET['sort']`.
- **Implementasi PHP** pada [`helpers/validation.php`](file:///c:/Users/ASUS/Documents/miniproject3/helpers/validation.php) dan [`public/products/index.php`](file:///c:/Users/ASUS/Documents/miniproject3/public/products/index.php):
  - Didefinisikan daftar putih kolom yang sah:
    ```php
    $allowedSortColumns = [
        'name'  => 'p.name',
        'sku'   => 'p.sku',
        'price' => 'p.price',
        'stock' => 'p.stock',
    ];
    $sort = validate_sort_column($_GET['sort'] ?? null, array_keys($allowedSortColumns), 'name');
    $order = validate_sort_direction($_GET['order'] ?? null, 'ASC');
    ```
  - Input berbahaya seperti `price; DROP TABLE products; --` secara otomatis dinetralkan kembali ke nilai bawaan `'name'`.
