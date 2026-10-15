<?php
/**
 * Flash Message Helper
 * Menyimpan notifikasi sementara di session dan merendernya dengan rapi
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Set pesan flash baru
 *
 * @param string $type success|danger|warning|info
 * @param string $message
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Ambil semua pesan flash dan hapus dari session
 *
 * @return array
 */
function get_flash(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Render notifikasi flash dalam bentuk komponen alert Bootstrap
 */
function render_flash(): void
{
    $messages = get_flash();
    if (empty($messages)) {
        return;
    }

    echo '<div class="flash-messages mb-4">';
    foreach ($messages as $msg) {
        $alertType = htmlspecialchars($msg['type'], ENT_QUOTES, 'UTF-8');
        $icon = match($alertType) {
            'success' => 'bi-check-circle-fill',
            'danger'  => 'bi-exclamation-triangle-fill',
            'warning' => 'bi-exclamation-circle-fill',
            default   => 'bi-info-circle-fill',
        };
        echo '<div class="alert alert-' . $alertType . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">';
        echo '  <i class="bi ' . $icon . ' fs-5 me-2"></i>';
        echo '  <div class="flex-grow-1">' . htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8') . '</div>';
        echo '  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    echo '</div>';
}
