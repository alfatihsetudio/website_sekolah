<?php
/**
 * Authentication & Authorization Helper (Hardened)
 * - Menggunakan getDB() dari inc/db.php (mysqli)
 * - Fitur: session fingerprint, inactivity timeout, CSRF helpers, safer redirects
 * - Multi-sekolah: menyimpan school_id di session
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default session timeout (seconds) - 30 minutes
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 30 * 60);
}

/*
|--------------------------------------------------------------------------
| SESSION / SECURITY HELPERS
|--------------------------------------------------------------------------
*/

/**
 * Buat fingerprint sederhana untuk cek session hijack
 */
function _session_fingerprint() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return hash('sha256', $ua . '|' . $ip);
}

/**
 * Inisialisasi security session setelah login sukses
 */
function _init_session_security() {
    $_SESSION['last_activity'] = time();
    $_SESSION['fingerprint']   = _session_fingerprint();
    // session_regenerate_id(true) sudah dipanggil dalam loginUser
}

/**
 * Cek apakah session masih valid (timeout + fingerprint)
 */
function _is_session_valid() {
    if (!isset($_SESSION['last_activity'])) return false;
    if ((time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) return false;
    if (!isset($_SESSION['fingerprint'])) return false;
    if ($_SESSION['fingerprint'] !== _session_fingerprint()) return false;
    return true;
}

/**
 * Update last activity timestamp (panggil di setiap halaman yg butuh auth)
 */
function touchSession() {
    if (isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
}

/*
|--------------------------------------------------------------------------
| AUTH STATUS HELPERS
|--------------------------------------------------------------------------
*/

function isLoggedIn() {
    // user_id harus ada dan session masih valid
    return !empty($_SESSION['user_id']) && _is_session_valid();
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

/**
 * Beberapa bagian aplikasi kadang pakai 'murid', kadang 'siswa'
 */
function isMurid() {
    $role = getUserRole();
    return $role === 'murid' || $role === 'siswa';
}

/*
|--------------------------------------------------------------------------
| REDIRECT HELPERS
|--------------------------------------------------------------------------
*/

function safeRedirect($path) {
    header('Location: ' . $path);
    exit;
}

/**
 * Require login - redirect ke login jika belum login / session invalid
 */
function requireLogin() {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/\\') : '';

    if (!isLoggedIn()) {
        // pastikan session bersih jika expired / tidak valid
        logoutUser();
        safeRedirect($base . '/auth/login.php');
    } else {
        // update last activity
        touchSession();
    }
}

/**
 * Require role tertentu
 * Jika tidak sesuai -> kirim 403 Forbidden
 */
function requireRole($roles) {
    if (!isLoggedIn()) {
        requireLogin(); // ini akan redirect & exit
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

/*
|--------------------------------------------------------------------------
| LOGIN / LOGOUT
|--------------------------------------------------------------------------
*/

/**
 * Login user
 * - Mengisi: user_id, email, nama, role, school_id di session
 * @return bool
 */
function loginUser($email, $password) {
    // basic validation
    if (empty($email) || empty($password)) return false;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

    $db = getDB();

    $sql  = "SELECT id, email, password, nama, role, school_id FROM users WHERE email = ? LIMIT 1";
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

            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['nama']      = $user['nama'];
            $_SESSION['role']      = $user['role'];
            // PENTING: multi-sekolah
            $_SESSION['school_id'] = isset($user['school_id']) ? (int)$user['school_id'] : 0;

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
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // destroy session
    session_destroy();

    // mulai session baru agar tidak reuse ID (harden)
    session_start();
    session_regenerate_id(true);
}

/*
|--------------------------------------------------------------------------
| USER INFO
|--------------------------------------------------------------------------
*/

/**
 * Ambil data user saat ini dari DB
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;

    $db      = getDB();
    $user_id = (int)($_SESSION['user_id'] ?? 0);

    $sql  = "SELECT id, email, nama, role, school_id FROM users WHERE id = ? LIMIT 1";
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
 * Ambil school_id dari session (0 jika tidak ada)
 */
function getCurrentSchoolId(): int {
    return isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : 0;
}

/*
|--------------------------------------------------------------------------
| REDIRECT BERDASARKAN ROLE
|--------------------------------------------------------------------------
*/

/**
 * Redirect berdasarkan role (panggil setelah login sukses)
 */
function redirectByRole($role = null) {
    if ($role === null) {
        $role = $_SESSION['role'] ?? '';
    }

    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/\\') : '';

    if ($role === 'guru') {
        header('Location: ' . $base . '/guru.php');
    } elseif ($role === 'murid' || $role === 'siswa') {
        header('Location: ' . $base . '/murid.php');
    } elseif ($role === 'admin') {
        header('Location: ' . $base . '/dashboard/admin.php');
    } else {
        header('Location: ' . $base . '/index.php');
    }
    exit;
}
