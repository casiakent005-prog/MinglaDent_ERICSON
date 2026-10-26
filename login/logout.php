<?php
require_once __DIR__ . '/../database/config.php';
if (is_string($_COOKIE['remember_token'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $_COOKIE['remember_token'])) {
    $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE token = ?');
    $stmt->execute([hash('sha256', $_COOKIE['remember_token'])]);
}
setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
redirect('../index.php');
