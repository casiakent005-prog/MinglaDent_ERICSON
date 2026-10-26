<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

try {
    $pdo = new PDO(
        getenv('MINGLADENT_DB_DSN') ?: 'mysql:host=localhost;dbname=mingladent;charset=utf8mb4',
        getenv('MINGLADENT_DB_USER') ?: 'root',
        getenv('MINGLADENT_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    error_log('MinglaDent database connection failed.');
    http_response_code(503);
    exit('The service is temporarily unavailable. Please try again later.');
}

function getCurrentUser() {
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM auth_users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

require_once __DIR__ . '/../includes/auth.php';
authRestoreRememberSession();

// Refresh authority from the database on every request, including existing sessions.
$currentAccount = getCurrentUser();
if (isset($_SESSION['user_id'])) {
    if (!$currentAccount || !in_array($currentAccount['status'], ['active', 'pending'], true)
        || ($currentAccount['status'] === 'pending' && $currentAccount['role'] !== 'clinic')) {
        $_SESSION = [];
    } else {
        $_SESSION['role'] = $currentAccount['role'];
        $_SESSION['name'] = $currentAccount['name'];
        $_SESSION['email'] = $currentAccount['email'];
        unset($_SESSION['clinic_id'], $_SESSION['staff_role']);
    }
}
function isLoggedIn() { return isset($_SESSION['user_id']); }
function hasRole($role) { return isLoggedIn() && ($_SESSION['role'] ?? '') === $role; }
function redirect($url) { header('Location: ' . $url); exit; }
