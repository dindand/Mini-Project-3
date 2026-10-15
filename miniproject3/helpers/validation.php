<?php
/**
 * Input Validation Helpers
 * Syntactic & Semantic Validation sesuai Slide 21 & Business Rules Slide 33
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Validasi apakah teks terisi (tidak kosong setelah di-trim)
 */
function validate_required(?string $value, string $field_name = 'Field'): ?string
{
    if ($value === null || trim($value) === '') {
        return "{$field_name} wajib diisi.";
    }
    return null;
}

/**
 * Validasi integer dengan batas minimum dan maksimum
 */
function validate_int($value, int $min = 0, ?int $max = null, string $field_name = 'Jumlah'): ?string
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    if ($filtered === false) {
        return "{$field_name} harus berupa angka bulat yang valid.";
    }
    if ($filtered < $min) {
        return "{$field_name} tidak boleh bernilai kurang dari {$min}.";
    }
    if ($max !== null && $filtered > $max) {
        return "{$field_name} tidak boleh melebihi {$max}.";
    }
    return null;
}

/**
 * Validasi nilai desimal/uang non-negatif
 */
function validate_decimal($value, float $min = 0.0, string $field_name = 'Harga'): ?string
{
    // Filter dan parse float
    if (!is_numeric($value)) {
        return "{$field_name} harus berupa angka yang valid.";
    }
    $floatVal = (float)$value;
    if ($floatVal < $min) {
        return "{$field_name} tidak boleh negatif (minimal {$min}).";
    }
    return null;
}

/**
 * Validasi format SKU dan keunikan di database
 * Semantic validation
 */
function validate_sku(string $sku, PDO $pdo, ?int $excludeProductId = null): ?string
{
    $sku = trim($sku);
    if ($sku === '') {
        return "SKU wajib diisi.";
    }
    if (strlen($sku) > 50) {
        return "SKU maksimal 50 karakter.";
    }
    // Cek keunikan di database
    $sql = "SELECT id FROM products WHERE sku = :sku";
    $params = ['sku' => $sku];
    if ($excludeProductId !== null) {
        $sql .= " AND id != :exclude_id";
        $params['exclude_id'] = $excludeProductId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        return "SKU '{$sku}' sudah digunakan oleh produk lain. SKU harus unik.";
    }
    return null;
}

/**
 * Validasi kolom sorting dengan allowlist untuk mencegah SQL Injection via ORDER BY
 * Sesuai Slide 16
 */
function validate_sort_column(?string $column, array $allowlist, string $default): string
{
    if ($column !== null && in_array($column, $allowlist, true)) {
        return $column;
    }
    return $default;
}

/**
 * Validasi arah sorting (ASC atau DESC)
 * Sesuai Slide 16
 */
function validate_sort_direction(?string $direction, string $default = 'ASC'): string
{
    $dir = strtoupper(trim((string)$direction));
    if (in_array($dir, ['ASC', 'DESC'], true)) {
        return $dir;
    }
    return $default;
}

/**
 * Generate CSRF Token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render input CSRF tersembunyi
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validasi CSRF Token dari form request
 */
function validate_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
