<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Web Pembelajaran');
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

$pageTitle = $pageTitle ?? APP_NAME;
$baseUrl   = rtrim(BASE_URL, '/\\');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo sanitize($pageTitle) . ' - ' . sanitize(APP_NAME); ?></title>

    <!-- CSS Global -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/style.css">

    <!-- Tambahan CSS Global Minimal (agar layout dasar tetap rapi) -->
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f9fafb;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #111827;
        }

        main {
            max-width: 1000px;
            margin: 24px auto;
            padding: 0 16px;
        }

        /* Utility umum */
        .card {
            background: #ffffff;
            padding: 16px;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(15,23,42,0.08);
        }

        a {
            color: #2563eb;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        table th, table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        table thead tr {
            background: #f3f4f6;
        }
    </style>
</head>
<body>
<main>
