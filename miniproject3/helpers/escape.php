<?php
/**
 * Output Escaping & Formatting Helpers
 * Menghindari risiko XSS (Cross-Site Scripting) sesuai Slide 20
 */

if (!function_exists('e')) {
    /**
     * Escape output string untuk aman dirender di HTML
     * Menggunakan htmlspecialchars dengan ENT_QUOTES dan UTF-8
     *
     * @param mixed $string
     * @return string
     */
    function e($string): string
    {
        return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_rupiah')) {
    /**
     * Format nilai angka menjadi format mata uang Rupiah
     *
     * @param float|int|string $amount
     * @return string
     */
    function format_rupiah($amount): string
    {
        return 'Rp ' . number_format((float)$amount, 0, ',', '.');
    }
}

if (!function_exists('format_datetime')) {
    /**
     * Format tanggal dan waktu ke format Indonesia
     *
     * @param string|null $datetime
     * @return string
     */
    function format_datetime(?string $datetime): string
    {
        if (!$datetime) return '-';
        $time = strtotime($datetime);
        return date('d/m/Y H:i', $time);
    }
}

if (!function_exists('base_url')) {
    /**
     * Mendapatkan base URL aplikasi secara dinamis
     * Mendukung running via php -S localhost:8000 (dari root atau folder public) maupun Apache XAMPP
     *
     * @param string $path
     * @return string
     */
    function base_url(string $path = ''): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Cari folder 'public' di scriptName
        $pos = strpos($scriptName, '/public/');
        if ($pos !== false) {
            $base = substr($scriptName, 0, $pos + 8); // sampai '/public/'
        } else if (substr($scriptName, -10) === '/public.php' || substr($scriptName, -7) === '/public') {
            $base = dirname($scriptName) . '/public/';
        } else {
            // Jika document root sudah diarahkan ke folder public atau project root
            $dir = dirname($scriptName);
            $base = ($dir === '/' || $dir === '\\') ? '/' : rtrim($dir, '/\\') . '/';
            // Jika script ada di subfolder seperti /products/index.php, cari letak public
            if (strpos($base, '/products/') !== false || strpos($base, '/stock/') !== false ||
                strpos($base, '/categories/') !== false || strpos($base, '/suppliers/') !== false ||
                strpos($base, '/product-suppliers/') !== false || strpos($base, '/users/') !== false ||
                strpos($base, '/reports/') !== false) {
                $base = preg_replace('#/(products|stock|categories|suppliers|product-suppliers|users|reports)/.*#', '/', $base);
            }
        }
        
        $base = rtrim($base, '/') . '/';
        return $base . ltrim($path, '/');
    }
}
