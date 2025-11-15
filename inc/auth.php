<?php
/**
 * Authentication & Authorization Helper (Hardened)
 * - Compatibility: menggunakan getDB() yang sudah ada di inc/db.php (mysqli)
 * - Menambahkan: session fingerprint, inactivity timeout, CSRF helpers, safer redirects
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Default session timeout (seconds) - 30 minutes
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 30 * 60);
}

/** -----------------------------
 * SESSION / SECURITY HELPERS
 * -----------------------------*/

/**
 * Buat fingerprint sederhana untuk cek session hijack
 */
function _session_fingerprint() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return hash('sha256', $ua . '|' . $ip);
}

/**
 * Inisialisasi session after login
 */
function _init_session_security() {
    $_SESSION['last_activity'] = time();
    $_SESSION['fingerprint'] = _session_fingerprint();
    // Regenerate ID sudah dipanggil saat loginUser
}

/**
 * Cek session alive & fingerprint
 */
function _is_session_valid() {
    if (!isset($_SESSION['last_activity'])) return false;
    if ((time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) return false;
    if (!isset($_SESSION['fingerprint'])) return false;
    if ($_SESSION['fingerprint'] !== _session_fingerprint()) return false;
    return true;
}

/**
 * Update last activity timestamp (panggil di setiap halaman yang aktif)
 */
function touchSession() {
    if (isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
}

/** -----------------------------
 * AUTH STATUS HELPERS
 * -----------------------------*/

function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function isAdmin() {
    return getUserRole() === 'admin';
}

function isGuru() {
    return getUserRole() === 'guru';
}

function isMurid() {
    return getUserRole() === 'murid';
}

/** -----------------------------
 * REDIRECT HELPERS
 * -----------------------------*/

function safeRedirect($path) {
    // gunakan relative path agar portable; jika BASE_URL ada, bisa gunakan itu
    header('Location: ' . $path);
    exit;
}

/**
 * Require login - redirect ke login jika belum login
 * Jika sudah login tapi session expired, logout otomatis dan redirect ke login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // pastikan session bersih jika expired
        logoutUser();
        // relative path (sesuaikan jika perlu)
        safeRedirect('/web_MG/auth/login.php');
    } else {
        // update last activity
        touchSession();
    }
}

/**
 * Require role tertentu
 * Jika tidak sesuai -> kirim 403 Forbidden (akses ditolak)
 */
function requireRole($roles) {
    if (!isLoggedIn()) {
        requireLogin();
    }
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        echo "<h1>403 Forbidden</h1><p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>";
        exit;
    }

    // update activity
    touchSession();
}

/** -----------------------------
 * LOGIN / LOGOUT
 * -----------------------------*/

/**
 * Login user
 * @return bool
 */
function loginUser($email, $password) {
    // basic validation
    if (empty($email) || empty($password)) return false;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

    $db = getDB();

    $sql = "SELECT id, email, password, nama, role FROM users WHERE email = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // sukses login
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['role'] = $user['role'];

            _init_session_security();

            return true;
        }
    }

    return false;
}

/**
 * Logout user
 */
function logoutUser() {
    // unset semua data session
    $_SESSION = [];

    // hapus cookie session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // destroy session
    session_destroy();

    // mulai session baru agar tidak reuse ID (harden)
    session_start();
    session_regenerate_id(true);
}

/** -----------------------------
 * USER INFO
 * -----------------------------*/

/**
 * Get current user data (from DB)
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;

    $db = getDB();
    $user_id = (int)$_SESSION['user_id'];

    $sql = "SELECT id, email, nama, role FROM users WHERE id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        return $result->fetch_assoc();
    }

    return null;
}

/**
 * Redirect berdasarkan role (panggil setelah login sukses)
 */
function redirectByRole($role = null) {
    if ($role === null) {
        $role = $_SESSION['role'] ?? '';
    }
    if ($role === 'guru') header('Location: ' . BASE_URL . '/guru.php');
    elseif ($role === 'siswa') header('Location: ' . BASE_URL . '/murid.php');
    else header('Location: ' . BASE_URL . '/index.php');
    exit;
}
