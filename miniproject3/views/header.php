<?php
/**
 * Header Layout Template
 * Mini Project 3 - Inventory Management System
 */

require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/flash.php';

$pageTitle = isset($title) ? $title . " - Inventory System" : "Inventory Management System";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f4f6f9;
            color: #2b3445;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .card {
            border: 1px solid rgba(0,0,0,0.06);
            border-radius: 0.75rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.04);
        }
        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            font-weight: 600;
            border-top-left-radius: 0.75rem !important;
            border-top-right-radius: 0.75rem !important;
        }
        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.08);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .table > :not(caption) > * > * {
            padding: 0.85rem 0.85rem;
            vertical-align: middle;
        }
        .badge-in {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }
        .badge-out {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }
        .badge-low-stock {
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
        }
        .dropdown-item.active, .dropdown-item:active {
            background-color: #0d6efd;
        }
        footer {
            margin-top: auto;
            border-top: 1px solid #e5e7eb;
            background-color: #ffffff;
        }
    </style>
</head>
<body>
<?php if (is_logged_in()): ?>
    <?php require __DIR__ . '/navbar.php'; ?>
<?php endif; ?>

<main class="container-fluid px-md-4 py-4 flex-grow-1">
    <?php render_flash(); ?>
