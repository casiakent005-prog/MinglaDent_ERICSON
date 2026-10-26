<?php
require_once '../database/config.php';
require_once '../database/social_auth_config.php';



$config = require '../database/social_auth_config.php';
$fbConfig = $config['facebook'];

// Check if we have an error from Facebook
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $error_description = $_GET['error_description'] ?? 'Unknown error';

    // Log the error
    error_log("Facebook Auth Error: {$error} - {$error_description}");

    // Redirect back to login with error message
    $_SESSION['auth_error'] = 'Facebook authentication failed or was cancelled.';
    header('Location: login_new.php');
    exit;
}

// Check if we have the authorization code
if (!isset($_GET['code'])) {
    $_SESSION['auth_error'] = 'No authorization code received from Facebook.';
    header('Location: login_new.php');
    exit;
}

$code = $_GET['code'];

try {
    // Exchange authorization code for access token
    $tokenUrl = 'https://graph.facebook.com/' . $fbConfig['graph_version'] . '/oauth/access_token';
    $tokenParams = [
        'client_id' => $fbConfig['app_id'],
        'client_secret' => $fbConfig['app_secret'],
        'redirect_uri' => $fbConfig['redirect_uri'],
        'code' => $code
    ];

    $tokenResponse = file_get_contents($tokenUrl . '?' . http_build_query($tokenParams));
    $tokenData = json_decode($tokenResponse, true);

    if (!isset($tokenData['access_token'])) {
        throw new Exception('Failed to obtain access token from Facebook');
    }

    $accessToken = $tokenData['access_token'];

    // Get user info from Facebook Graph API
    $userUrl = 'https://graph.facebook.com/' . $fbConfig['graph_version'] . '/me';
    $userParams = [
        'fields' => 'id,name,email,first_name,last_name',
        'access_token' => $accessToken
    ];

    $userResponse = file_get_contents($userUrl . '?' . http_build_query($userParams));
    $userData = json_decode($userResponse, true);

    if (!isset($userData['id'])) {
        throw new Exception('Failed to obtain user information from Facebook');
    }

    $facebookId = $userData['id'];
    $email = $userData['email'] ?? null;
    $firstName = $userData['first_name'] ?? '';
    $lastName = $userData['last_name'] ?? '';
    $fullName = $userData['name'] ?? trim($firstName . ' ' . $lastName);

    // Check if user already exists with this Facebook ID
    $stmt = $pdo->prepare("SELECT * FROM auth_users WHERE auth_provider = 'facebook' AND provider_user_id = ? LIMIT 1");
    $stmt->execute([$facebookId]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        // User exists, log them in
        authStartSession($existingUser);

        // Log successful login
        $stmt = $pdo->prepare("INSERT INTO social_auth_log (user_id, auth_provider, provider_user_id, action, status, ip_address) VALUES (?, 'facebook', ?, 'login', 'success', ?)");
        $stmt->execute([$existingUser['id'], $facebookId, $_SERVER['REMOTE_ADDR']]);

        header('Location: ' . authDestination(getCurrentUser()));
        exit;
    }

    // Check if user exists with the same email
    if ($email) {
        $stmt = $pdo->prepare("SELECT * FROM auth_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $emailUser = $stmt->fetch();

        if ($emailUser) {
            // User exists with this email but not linked to Facebook
            // Link the Facebook account to existing user
            $stmt = $pdo->prepare("UPDATE auth_users SET auth_provider = 'facebook', provider_user_id = ? WHERE id = ?");
            $stmt->execute([$facebookId, $emailUser['id']]);

            // Log successful account linking
            $stmt = $pdo->prepare("INSERT INTO social_auth_log (user_id, auth_provider, provider_user_id, action, status, ip_address) VALUES (?, 'facebook', ?, 'link', 'success', ?)");
            $stmt->execute([$emailUser['id'], $facebookId, $_SERVER['REMOTE_ADDR']]);

            // Log them in
            authStartSession($emailUser);

            header('Location: ' . authDestination(getCurrentUser()));
            exit;
        }
    }

    // New user - create account
    $pdo->beginTransaction();

    // Generate a random password for the user (they won't use it)
    $randomPassword = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("INSERT INTO auth_users (name, first_name, last_name, email, password, role, status, auth_provider, provider_user_id) VALUES (?, ?, ?, ?, ?, 'patient', 'active', 'facebook', ?)");
    $stmt->execute([
        $fullName,
        $firstName ?: null,
        $lastName ?: null,
        $email ?: null,
        password_hash($randomPassword, PASSWORD_DEFAULT),
        $facebookId
    ]);

    $userId = (int) $pdo->lastInsertId();

    // Create patient profile
    $stmt = $pdo->prepare("INSERT INTO patient_profiles (user_id, privacy_consent_at) VALUES (?, NOW())");
    $stmt->execute([$userId]);

    $pdo->commit();

    // Log successful registration
    $stmt = $pdo->prepare("INSERT INTO social_auth_log (user_id, auth_provider, provider_user_id, action, status, ip_address) VALUES (?, 'facebook', ?, 'register', 'success', ?)");
    $stmt->execute([$userId, $facebookId, $_SERVER['REMOTE_ADDR']]);

    // Log them in
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['name'] = $fullName;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = 'patient';
    $_SESSION['staff_role'] = null;
    $_SESSION['clinic_id'] = null;

    header('Location: ' . authDestination(getCurrentUser()));
    exit;

} catch (Exception $e) {
    error_log("Facebook Auth Exception: " . $e->getMessage());

    // Log failed attempt
    if (isset($facebookId)) {
        $stmt = $pdo->prepare("INSERT INTO social_auth_log (auth_provider, provider_user_id, action, status, error_message, ip_address) VALUES ('facebook', ?, 'login', 'failed', ?, ?)");
        $stmt->execute([$facebookId ?? null, $e->getMessage(), $_SERVER['REMOTE_ADDR']]);
    }

    $_SESSION['auth_error'] = 'Facebook authentication failed. Please try again.';
    header('Location: login_new.php');
    exit;
}
?>
