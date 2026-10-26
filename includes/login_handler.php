<?php
require_once __DIR__ . '/../database/config.php';
$message = $_SESSION['registration_success'] ?? '';
$messageType = $message ? 'success' : 'error';
unset($_SESSION['registration_success']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    authRequireCsrf();
    $identifier = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = $_POST['password'] ?? '';
    if ($identifier === '' || !is_string($password) || $password === '') {
        $message = 'Email or username and password are required.';
        $messageType = 'error';
    } else {
        try {
            // Usernames cannot contain @, so an identifier cannot select two accounts.
            $column = str_contains($identifier, '@') ? 'email' : 'username';
            $stmt = $pdo->prepare("SELECT * FROM auth_users WHERE $column = ? LIMIT 1");
            $stmt->execute([$identifier]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password'])
                || !in_array($user['status'], ['active', 'pending'], true)
                || ($user['status'] === 'pending' && $user['role'] !== 'clinic')) {
                $message = 'Invalid credentials or inactive account.';
                $messageType = 'error';
            } else {
                authStartSession($user);
                if (isset($_POST['remember']) && $user['status'] === 'active') {
                    $token = bin2hex(random_bytes(32));
                    $stmt = $pdo->prepare('INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
                    $stmt->execute([$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 2592000)]);
                    setcookie('remember_token', $token, ['expires' => time() + 2592000, 'path' => '/',
                        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
                }
                redirect(authDestination($user));
            }
        } catch (PDOException $e) {
            $message = 'Login is temporarily unavailable. Please try again.';
            $messageType = 'error';
            error_log('Login database error (' . $e->getCode() . ').');
        }
    }
}
