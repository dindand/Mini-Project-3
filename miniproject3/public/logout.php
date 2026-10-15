<?php
/**
 * Logout Pengguna
 */

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/flash.php';

logout_user();

// Mulai session baru hanya untuk flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
set_flash('info', 'Anda telah berhasil keluar dari sistem.');
header('Location: login.php');
exit;
