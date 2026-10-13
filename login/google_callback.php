<?php
require_once '../database/config.php';
require_once '../database/social_auth_config.php';

session_start();

$config = require '../database/social_auth_config.php';
$googleConfig = $config['google'];

// Check if we have an error from Google
if (isset($_GET['error'])) {
    $error = $_GET['error'];

    // Log the error
    error_log("Google Auth Error: {$error}");

    // Redirect back to login with error message
    $_SESSION['auth_error'] = 'Google authentication failed or was cancelled.';
    header('Location: login_new.php');
    exit;
}

// Check if we have the authorization code
if (!isset($_GET['code'])) {
    $_SESSION['auth_error'] = 'No authorization code received from Google.';
    header('Location: login_new.php');
    exit;
}

$code = $_GET['code'];

try {
    // Exchange authorization code for access token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $tokenParams = [
        'code' => $code,
        'client_id' => $googleConfig['client_id'],
        'client_secret' => $googleConfig['client_secret'],
        'redirect_uri' => $googleConfig['redirect_uri'],
        'grant_type' => 'authorization_code'
    ];

    $tokenResponse = postRequest($tokenUrl, $tokenParams);
    $tokenData = json_decode($tokenResponse, true);

    if (!isset($tokenData['access_token'])) {
        throw new Exception('Failed to obtain access token from Google');
    }

    $accessToken = $tokenData['access_token'];

    // Get user info from Google
    $userUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $userResponse = getRequest($userUrl, ['Authorization: Bearer ' . $accessToken]);
    $userData = json_decode($userResponse, true);

    if (!isset($userData['id'])) {
        throw new Exception('Failed to obtain user information from Google');
    }

    $googleId = $userData['id'];
    $email = $userData['email'] ?? null;
    $firstName = $userData['given_name'] ?? '';
    $lastName = $userData['family_name'] ?? '';
    $fullName = $userData['name'] ?? trim($firstName . ' ' . $lastName);

    // Check if user already exists with this Google ID
    $stmt = $pdo->prepare("SELECT * FROM auth_users WHERE auth_provider = 'google' AND provider_user_id = ? LIMIT 1");
    $stmt->execute([$googleId]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        // User exists, log them in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $existingUser['id'];
        $_SESSION['name'] = $existingUser['name'];
        $_SESSION['email'] = $existingUser['email'];
        $_SESSION['role'] = $existingUser['role'];
        $_SESSION['staff_role'] = null;
        $_SESSION['clinic_id'] = $existingUser['clinic_id'] ?? null;

        // Log successful login
        $stmt = $pdo->prepare("INSERT INTO social_auth_log (user_id, auth_provider, provider_user_id, action, status, ip_address) VALUES (?, 'google', ?, 'login', 'success', ?)");
        $stmt->execute([$existingUser['id'], $googleId, $_SERVER['REMOTE_ADDR']]);

        header('Location: ../index.php');
        exit;
    }

    // Check if user exists with the same email
    if ($email) {
        $stmt = $pdo->prepare("SELECT * FROM auth_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $emailUser = $stmt->fetch();

        if ($emailUser) {
            // User exists with this email but not linked to Google
            // Link the Google account to existing user
            $stmt = $pdo->prepare("UPDATE auth_users SET auth_provider = 'google', provider_user_id = ? WHERE id = ?");
            $stmt->execute([$googleId, $emailUser['id']]);

            // Log successful account linking
            $stmt = $pdo->prepare("INSERT INTO social_auth_log (user_id, auth_provider, provider_user_id, action, status, ip_address) VALUES (?, 'google', ?, 'link', 'success', ?)");
            $stmt->execute([$emailUser['id'], $googleId, $_SERVER['REMOTE_ADDR']]);

            // Log them in
            session_regenerate_id(true);
            $_SESSION['user_id'] = $emailUser['id'];
            $_SESSION['name'] = $emailUser['name'];
            $_SESSION['email'] = $emailUser['email'];
            $_SESSION['role'] = $emailUser['role'];
            $_SESSION['staff_role'] = null;
            $_SESSION['clinic_id'] = $emailUser['clinic_id'] ?? null;

            header('Location: ../index.php');
            exit;
        }
    }

    // New user - create account
    $pdo->beginTransaction();

    // Generate a random password for the user (they won't use it)
    $randomPassword = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("INSERT INTO auth_users (name, first_name, last_name, email, password, role, status, auth_provider, provider_user_id) VALUES (?, ?, ?, ?, ?, 'patient', 'active', 'google', ?)");
    $stmt->execute([
        $fullName,
        $firstName ?: null,
        $lastName ?: null,
        $email ?: null,
        password_hash($randomPassword, PASSWORD_DEFAULT),
        $googleId
    ]);

    $userId = (int) $pdo->lastInsertId();

    // Create patient profile
    $stmt = $pdo->prepare("INSERT INTO patient_profiles (user_id, privacy_consent_at) VALUES (?, NOW())");
    $stmt->execute([$userId]);

    $pdo->commit();

    // Log successful registration
    $stmt = $pdo->prepare("INSERT INTO social_auth_log (user_id, auth_provider, provider_user_id, action, status, ip_address) VALUES (?, 'google', ?, 'register', 'success', ?)");
    $stmt->execute([$userId, $googleId, $_SERVER['REMOTE_ADDR']]);

    // Log them in
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['name'] = $fullName;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = 'patient';
    $_SESSION['staff_role'] = null;
    $_SESSION['clinic_id'] = null;

    header('Location: ../index.php');
    exit;

} catch (Exception $e) {
    error_log("Google Auth Exception: " . $e->getMessage());

    // Log failed attempt
    if (isset($googleId)) {
        $stmt = $pdo->prepare("INSERT INTO social_auth_log (auth_provider, provider_user_id, action, status, error_message, ip_address) VALUES ('google', ?, 'login', 'failed', ?, ?)");
        $stmt->execute([$googleId ?? null, $e->getMessage(), $_SERVER['REMOTE_ADDR']]);
    }

    $_SESSION['auth_error'] = 'Google authentication failed. Please try again.';
    header('Location: login_new.php');
    exit;
}

// Helper function for POST requests
function postRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Helper function for GET requests with headers
function getRequest($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}
?>
