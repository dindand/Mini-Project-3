# Entity-Relationship Diagram (ERD) & Desain Database

**Mini Project 3 — Inventory Management System**  
Mata Kuliah / Materi: *Pertemuan 4: Relational Database + Advanced PHP*

---

## 1. Diagram Relasi Entitas (Mermaid ERD)

```mermaid
erDiagram
    users ||--|| user_profiles : "1-to-1 (user_id UNIQUE)"
    categories ||--|{ products : "1-to-Many (category_id)"
    products ||--|{ product_suppliers : "Many-to-Many Junction"
    suppliers ||--|{ product_suppliers : "Many-to-Many Junction"
    products ||--|{ stock_movements : "1-to-Many (product_id)"
    users ||--|{ stock_movements : "1-to-Many (user_id)"

    users {
        int id PK "AUTO_INCREMENT"
        varchar username UK "50, Unique"
        varchar password_hash "255, Bcrypt"
        enum role "admin, staff"
        timestamp created_at
    }

    user_profiles {
        int id PK "AUTO_INCREMENT"
        int user_id FK, UK "Unique 1-to-1"
        varchar full_name "100"
        varchar phone "20"
        text address
        timestamp created_at
    }

    categories {
        int id PK "AUTO_INCREMENT"
        varchar name UK "100, Unique"
        text description
        timestamp created_at
    }

    products {
        int id PK "AUTO_INCREMENT"
        int category_id FK "References categories(id)"
        varchar sku UK "50, Unique"
        varchar name "150"
        decimal price "12,2"
        int stock "Default 0"
        int minimum_stock "Default 5"
        timestamp created_at
        timestamp updated_at
    }

    suppliers {
        int id PK "AUTO_INCREMENT"
        varchar name "150"
        varchar contact_person "100"
        varchar phone "20"
        varchar email "100"
        text address
        timestamp created_at
    }

    product_suppliers {
        int product_id PK, FK "References products(id)"
        int supplier_id PK, FK "References suppliers(id)"
        decimal supplier_price "12,2"
        timestamp created_at
    }

    stock_movements {
        int id PK "AUTO_INCREMENT"
        int product_id FK "References products(id)"
        int user_id FK "References users(id)"
        enum type "IN, OUT"
        int quantity "Must > 0"
        text notes
        timestamp created_at
    }
```

---

## 2. Penjelasan Jenis Relasi Sesuai Slide Materi

### A. One-to-One (`users` &harr; `user_profiles`) — Slide 5 & 6
- **Konsep**: Satu akun user memiliki tepat satu data profil lengkap.
- **Implementasi**: Kolom `user_id` pada tabel `user_profiles` diberi batasan **`UNIQUE`** dan Foreign Key yang mengarah ke `users(id)`.
- **Aksi Referensial**: `ON DELETE CASCADE ON UPDATE CASCADE`. Jika akun user dihapus, profil terkait akan otomatis terhapus tanpa meninggalkan data yatim (*orphan records*).

### B. One-to-Many (`categories` &rarr; `products`) — Slide 5 & 7
- **Konsep**: Satu kategori barang dapat menaungi banyak produk, namun satu produk hanya dimiliki oleh satu kategori.
- **Implementasi**: Foreign key `category_id` diletakkan pada tabel anak (`products`).
- **Aksi Referensial**: `ON DELETE RESTRICT ON UPDATE CASCADE`. Kategori tidak boleh dihapus selama masih terdapat produk di dalamnya, mencegah produk kehilangan klasifikasi.

### C. Many-to-Many (`products` &harr; `suppliers`) — Slide 5 & 8
- **Konsep**: Satu produk dapat dipasok oleh banyak supplier yang berbeda dengan kesepakatan harga beli masing-masing, dan satu supplier dapat menyuplai banyak jenis produk.
- **Implementasi**: Menggunakan tabel perantara (*junction table*) bernama `product_suppliers`.
- **Composite Primary Key**: `PRIMARY KEY (product_id, supplier_id)`. Mencegah terjadinya duplikasi pasangan produk-supplier yang sama.
- **Kolom Tambahan**: `supplier_price` (harga pasokan/beli khusus dari supplier tersebut).

### D. One-to-Many Transaksi (`products` &rarr; `stock_movements` & `users` &rarr; `stock_movements`) — Slide 10
- **Konsep**: Setiap transaksi mutasi barang (masuk/keluar) dicatat dalam tabel `stock_movements`.
- **Relasi**:
  1. `stock_movements.product_id` mereferensikan `products.id`.
  2. `stock_movements.user_id` mereferensikan `users.id` (siapa staf/admin yang mencatat mutasi).
- **Aksi Referensial**: `ON DELETE RESTRICT`. Produk atau user yang sudah memiliki riwayat mutasi **TIDAK BOLEH** dihapus sembarangan agar *audit trail* logistik dan keuangan tetap utuh.

---

## 3. Referential Integrity Matrix

| Tabel Asal | Kolom FK | Tabel Referensi | On Delete | On Update | Tujuan Bisnis |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `user_profiles` | `user_id` | `users(id)` | **CASCADE** | **CASCADE** | Profil otomatis terhapus jika akun user dihapus |
| `products` | `category_id` | `categories(id)` | **RESTRICT** | **CASCADE** | Mencegah kategori dihapus jika masih ada produk |
| `product_suppliers` | `product_id` | `products(id)` | **CASCADE** | **CASCADE** | Menghapus pemetaan jika master produk dihapus |
| `product_suppliers` | `supplier_id` | `suppliers(id)` | **RESTRICT** | **CASCADE** | Mencegah supplier dihapus jika masih aktif menyuplai barang |
| `stock_movements` | `product_id` | `products(id)` | **RESTRICT** | **CASCADE** | Melindungi riwayat mutasi fisik barang |
| `stock_movements` | `user_id` | `users(id)` | **RESTRICT** | **CASCADE** | Melindungi pertanggungjawaban petugas operasional |
