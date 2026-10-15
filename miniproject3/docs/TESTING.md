# Panduan Pengujian & Verifikasi Fitur

**Mini Project 3 — Inventory Management System**  
Mata Kuliah / Materi: *Pertemuan 4: Relational Database + Advanced PHP*

Dokumen ini memuat skenario pengujian manual dan otomatis sesuai ketentuan **Checklist Pengumpulan (Slide 36)** dan **Tugas Pertemuan (Slide 38)**:
> *"Uji minimal satu kasus validation error dan satu kasus akses terlarang."*

---

## 1. Menjalankan Automated Test Suite (Pengujian Otomatis)

Proyek ini telah dilengkapi skrip pengujian otomatis berbasis CLI yang mencakup 10 skenario pengujian (13 assertions) mulai dari integritas relasi, transaksi atomik ACID, validasi semantik, hingga pengamanan SQL Injection dan XSS.

Jalankan perintah berikut di terminal:
```bash
# Pastikan berada di folder proyek
php tests/test_runner.php
```

### Hasil Eksekusi Test Runner:
```text
========================================================
  MINI PROJECT 3: AUTOMATED TEST & VERIFICATION SUITE   
========================================================

--- UJI 1: Skema Database & Tabel ---
[PASS] Semua 7 tabel utama telah terbuat di database

--- UJI 2: Otentikasi & Password Hashing ---
[PASS] Password hash admin dapat diverifikasi dengan password_verify()

--- UJI 3: Kasus Validation Error (SKU Duplikat & Angka Negatif) ---
[PASS] Validasi error muncul saat input SKU yang sudah terdaftar (SKU Unik)
[PASS] Validasi error muncul saat input harga bernilai negatif
[PASS] Validasi error muncul saat input kuantitas 0 pada transaksi barang masuk

--- UJI 4: Business Logic Barang Keluar Melebihi Stok (Rollback) ---
[PASS] Transaksi dibatalkan (Rollback) saat barang keluar melebihi stok
[PASS] Stok produk tidak berubah dan tidak menjadi negatif setelah rollback

--- UJI 5: Transaksi Barang Masuk (Atomic Commit) ---
[PASS] Transaksi barang masuk berhasil di-commit bersamaan dengan tabel histori

--- UJI 6: Integritas Referensial (ON DELETE RESTRICT) ---
[PASS] Database menolak DELETE produk berhistori karena relasi FK RESTRICT

--- UJI 7: Otorisasi & Akses Terlarang (Role 403 Forbidden) ---
[PASS] Pengguna berstatus 'staff' dilarang mengakses halaman admin (403 Forbidden)

--- UJI 8: Pencegahan XSS & Output Escaping ---
[PASS] Payload skrip berbahaya berhasil di-escape dengan htmlspecialchars

--- UJI 9: Pencegahan SQL Injection pada Sorting Allowlist ---
[PASS] Input sorting berbahaya dinetralkan kembali ke nilai default allowlist

--- UJI 10: Konsistensi Rumus Stok Akhir ---
[PASS] Seluruh produk memenuhi rumus: stok akhir = total IN - total OUT

========================================================
  HASIL AKHIR: 13 BERHASIL, 0 GAGAL      
========================================================
SELURUH PENGUJIAN SISTEM LULUS 100% SESUAI SPESIFIKASI!
```

---

## 2. Skenario Pengujian Kasus Wajib (Slide 38)

### Kasus A: Pengujian Kasus Validation Error
1. **Langkah 1**: Masuk sebagai Admin atau Staff.
2. **Langkah 2**: Buka menu **Transaksi Stok** &rarr; **Barang Keluar (OUT)**.
3. **Langkah 3**: Pilih produk dengan sisa stok rendah, contoh: `[PRD-ACC-002] Wireless Mouse Ergonomic Silent Click` (Sisa stok: **2 unit**).
4. **Langkah 4**: Masukkan jumlah pengeluaran yang melebihi stok, misalnya **50 unit**.
5. **Langkah 5**: Klik tombol **Simpan Transaksi Barang Keluar**.
6. **Hasil yang Terjadi**:
   - Sistem menolak transaksi dan mengeksekusi `$pdo->rollBack()`.
   - Muncul banner peringatan merah:
     > *"Validasi Gagal (Business Rule): Jumlah barang keluar (50 unit) melebihi stok yang tersedia saat ini (2 unit). Stok tidak boleh bernilai negatif!"*
   - Sisa stok produk di database tetap utuh 2 unit (tidak berkurang dan tidak menjadi negatif).

---

### Kasus B: Pengujian Kasus Akses Terlarang (HTTP 403 Forbidden)
1. **Langkah 1**: Masuk menggunakan akun **Staff** (`username: staff`, `password: password123`).
2. **Langkah 2**: Perhatikan menu navigasi atas. Menu "Kelola User" secara otomatis disembunyikan untuk staf.
3. **Langkah 3**: Lakukan percobaan pelanggaran izin (*bypass*) dengan mengetikkan URL administrasi user secara langsung pada address bar browser:
   ```text
   http://localhost:8080/public/users/index.php
   ```
4. **Hasil yang Terjadi**:
   - Sistem di server mengeksekusi `require_role('admin')` pada [`helpers/auth.php`](file:///c:/Users/ASUS/Documents/miniproject3/helpers/auth.php).
   - Server mengembalikan status code **HTTP 403 Forbidden**.
   - Tampil halaman peringatan terpusat:
     > **403 - Akses Ditolak (Forbidden)**  
     > *"Otorisasi Gagal. Anda tidak memiliki hak akses untuk membuka halaman administrasi ini. Halaman ini khusus untuk peran: admin. Role Anda saat ini: STAFF."*

---

## 3. Matriks Checklist Pengumpulan (Slide 36)

| No | Poin Checklist | Status | Bukti Implementasi / Berkas |
| :---: | :--- | :---: | :--- |
| 1 | **Relasi dan foreign key berjalan** | &check; **LULUS** | `database/schema.sql`, FK `ON DELETE RESTRICT` & `CASCADE` |
| 2 | **SKU unik dan validasi server aktif** | &check; **LULUS** | `helpers/validation.php`, `validate_sku()`, `validate_decimal()` |
| 3 | **Login, logout, session, dan role berjalan** | &check; **LULUS** | `helpers/auth.php`, `public/login.php`, `session_regenerate_id(true)` |
| 4 | **Tidak ada query concatenation dari input user** | &check; **LULUS** | Seluruh query menggunakan PDO Prepared Statements (`:param`) |
| 5 | **Output HTML menggunakan escaping** | &check; **LULUS** | `helpers/escape.php`, `e()` dengan `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` |
| 6 | **Search, filter, sorting, pagination berjalan** | &check; **LULUS** | `public/products/index.php`, allowlist sorting, paginasi limit & offset |
| 7 | **Stok tidak negatif dan histori tersimpan** | &check; **LULUS** | `public/stock/out.php`, `SELECT ... FOR UPDATE`, `stock_movements` |
| 8 | **Transaksi menggunakan commit/rollback** | &check; **LULUS** | `beginTransaction()`, `commit()`, `rollBack()` pada `in.php` & `out.php` |
| 9 | **Dashboard menampilkan laporan agregat** | &check; **LULUS** | `public/dashboard.php`, 5 widget agregat, GROUP BY & HAVING |
