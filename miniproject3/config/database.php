<?php
/**
 * Database Connection Configuration using PDO
 * Mini Project 3 - Inventory Management System
 */

// Konfigurasi Database
$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'inventory_db';
$db_user = 'root';
$db_pass = '';

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Menggunakan native prepared statements
    ];

    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Tampilkan pesan kesalahan yang informatif namun aman
    die("Koneksi Database Gagal: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
