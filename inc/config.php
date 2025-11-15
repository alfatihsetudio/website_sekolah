<?php
/**
 * Konfigurasi Aplikasi Web Pembelajaran
 * Edit sesuai dengan setting database InfinityFree Anda
 */

// Database Configuration
define('DB_HOST', '127.0.0.1'); // Ganti dengan hostname dari InfinityFree
define('DB_USER', 'root'); // Ganti dengan username database
define('DB_PASS', ''); // Ganti dengan password database
define('DB_NAME', 'web_markazlugoh'); // Ganti dengan nama database

// Application Settings
define('APP_NAME', 'Web MG');
define('BASE_PATH', rtrim(dirname(__DIR__), DIRECTORY_SEPARATOR));
define('BASE_URL', '/web_MG'); // Ganti dengan URL hosting Anda

// File Upload Settings
define('UPLOAD_DIR', BASE_PATH . '/uploads'); // pastikan folder writable oleh PHP
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip']);

// Session Settings
define('SESSION_LIFETIME', 3600 * 8); // 8 jam

// Pagination
define('ITEMS_PER_PAGE', 10);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error Reporting (set ke 0 untuk production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

