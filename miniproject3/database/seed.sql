-- =============================================================================
-- Mini Project 3: Inventory Management System
-- Pertemuan 4: Relational Database + Advanced PHP
-- Database Seed Data
-- =============================================================================

USE `inventory_db`;

-- Kosongkan data tabel (urutan child ke parent)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `stock_movements`;
TRUNCATE TABLE `product_suppliers`;
TRUNCATE TABLE `products`;
TRUNCATE TABLE `suppliers`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `user_profiles`;
TRUNCATE TABLE `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Insert Users (password default untuk admin & staff: 'password123')
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`) VALUES
(1, 'admin', '$2y$10$nuut9GWSoYm/Paqv/Dykoug6repgyGDKlKZv2lwV6eQWhjRVXhFxq', 'admin'),
(2, 'staff', '$2y$10$nuut9GWSoYm/Paqv/Dykoug6repgyGDKlKZv2lwV6eQWhjRVXhFxq', 'staff');

-- 2. Insert User Profiles (One-to-One dengan users)
INSERT INTO `user_profiles` (`id`, `user_id`, `full_name`, `phone`, `address`) VALUES
(1, 1, 'Administrator Utama', '081234567890', 'Gedung IT Lantai 3, Jakarta'),
(2, 2, 'Budi Santoso (Staff Gudang)', '089876543210', 'Gudang Logistik Blok B, Bekasi');

-- 3. Insert Categories (One-to-Many parent)
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Elektronik & Komputer', 'Perangkat komputer, laptop, monitor, dan aksesoris utama'),
(2, 'Aksesoris & Periferal', 'Keyboard, mouse, flashdisk, dan perlengkapan input/output'),
(3, 'Alat Tulis Kantor', 'Kertas, pena, map, dan peralatan operasional kantor harian'),
(4, 'Peralatan Jaringan', 'Router, switch, kabel LAN, crimping tool, dan konektor RJ45');

-- 4. Insert Suppliers
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`) VALUES
(1, 'PT Sumber Jaya Abadi', 'Hendra Setiawan', '021-5551234', 'info@sumberjaya.co.id', 'Jl. Industri Raya No. 45, Jakarta Pusat'),
(2, 'CV Maju Makmur Sentosa', 'Rina Kartika', '022-7778899', 'sales@majumakmur.com', 'Jl. Sukajadi No. 12, Bandung'),
(3, 'PT Global Tech Distribusi', 'Andi Pratama', '031-8889900', 'contact@globaltech.id', 'Jl. Pemuda No. 88, Surabaya');

-- 5. Insert Products
INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `price`, `stock`, `minimum_stock`) VALUES
(1, 1, 'PRD-ELEC-001', 'Laptop Lenovo ThinkPad T480 Core i5', 6500000.00, 12, 5),
(2, 1, 'PRD-ELEC-002', 'Monitor Dell 24 Inch IPS Full HD', 1850000.00, 4, 5),
(3, 2, 'PRD-ACC-001', 'Mechanical Keyboard RGB Red Switch', 450000.00, 15, 5),
(4, 2, 'PRD-ACC-002', 'Wireless Mouse Ergonomic Silent Click', 175000.00, 2, 5),
(5, 4, 'PRD-NET-001', 'Router WiFi Gigabit Dual-Band AC1200', 380000.00, 8, 5),
(6, 3, 'PRD-ATK-001', 'Kertas HVS PaperOne A4 80gr 1 Rim', 55000.00, 25, 10),
(7, 3, 'PRD-ATK-002', 'Ballpoint Gel Hitam 0.5mm (Pak 12 pcs)', 36000.00, 40, 10),
(8, 2, 'PRD-ACC-003', 'Flashdisk SanDisk Ultra USB 3.0 64GB', 85000.00, 3, 5);

-- 6. Insert Product Suppliers (Many-to-Many junction)
INSERT INTO `product_suppliers` (`product_id`, `supplier_id`, `supplier_price`) VALUES
(1, 1, 5800000.00),
(1, 3, 5950000.00),
(2, 3, 1600000.00),
(3, 2, 380000.00),
(4, 2, 140000.00),
(5, 3, 310000.00),
(6, 1, 48000.00),
(7, 1, 30000.00),
(8, 2, 70000.00);

-- 7. Insert Stock Movements (Histori mutasi yang menghasilkan angka stok akhir)
-- Rumus: stok akhir = total IN - total OUT
INSERT INTO `stock_movements` (`product_id`, `user_id`, `type`, `quantity`, `notes`, `created_at`) VALUES
-- Produk 1: ThinkPad (IN 15, OUT 3 = 12)
(1, 1, 'IN', 15, 'Stok awal pengadaan laptop divisi IT', '2026-10-01 09:00:00'),
(1, 2, 'OUT', 3, 'Distribusi laptop karyawan baru tim dev', '2026-10-05 14:15:00'),

-- Produk 2: Monitor Dell (IN 10, OUT 6 = 4, Low Stock!)
(2, 1, 'IN', 10, 'Pengadaan monitor kantor cabang', '2026-10-02 10:30:00'),
(2, 2, 'OUT', 6, 'Pemasangan display ruang meeting dan workstation', '2026-10-06 11:00:00'),

-- Produk 3: Mechanical Keyboard (IN 20, OUT 5 = 15)
(3, 1, 'IN', 20, 'Restock keyboard dari CV Maju Makmur', '2026-10-03 13:00:00'),
(3, 2, 'OUT', 5, 'Penggantian keyboard lab programming', '2026-10-07 15:30:00'),

-- Produk 4: Wireless Mouse (IN 10, OUT 8 = 2, Low Stock!)
(4, 1, 'IN', 10, 'Pengadaan perlengkapan peripheral', '2026-10-04 09:45:00'),
(4, 2, 'OUT', 8, 'Permintaan divisi marketing dan finance', '2026-10-08 16:00:00'),

-- Produk 5: Router WiFi (IN 12, OUT 4 = 8)
(5, 1, 'IN', 12, 'Penerimaan perangkat jaringan baru', '2026-10-04 11:20:00'),
(5, 2, 'OUT', 4, 'Instalasi access point lantai 2 dan 3', '2026-10-09 10:15:00'),

-- Produk 6: Kertas HVS (IN 30, OUT 5 = 25)
(6, 1, 'IN', 30, 'Stok bulanan kebutuhan ATK', '2026-10-05 08:30:00'),
(6, 2, 'OUT', 5, 'Kebutuhan cetak laporan bulanan direksi', '2026-10-10 13:45:00'),

-- Produk 7: Ballpoint Gel (IN 50, OUT 10 = 40)
(7, 1, 'IN', 50, 'Restock alat tulis', '2026-10-05 08:40:00'),
(7, 2, 'OUT', 10, 'Distribusi ATK seluruh divisi', '2026-10-11 09:00:00'),

-- Produk 8: Flashdisk SanDisk (IN 15, OUT 12 = 3, Low Stock!)
(8, 1, 'IN', 15, 'Pengadaan media penyimpanan eksternal', '2026-10-06 14:00:00'),
(8, 2, 'OUT', 12, 'Pembagian USB backup untuk koordinator unit', '2026-10-12 11:30:00');
