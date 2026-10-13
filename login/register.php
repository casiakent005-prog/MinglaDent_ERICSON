<?php
require_once '../database/config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
    $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $consentGiven = ($_POST['privacy_consent'] ?? '') === '1';
    $birthDate = $dateOfBirth === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $mobile === '') {
        $message = 'Enter your name, a valid email, and mobile number.';
        $messageType = 'error';
    } elseif (strlen($password) < 8 || $password !== $confirmPassword) {
        $message = 'Use a password with at least 8 characters and make both password fields match.';
        $messageType = 'error';
    } elseif (!$consentGiven) {
        $message = 'Consent to the privacy notice is required to create an account.';
        $messageType = 'error';
    } elseif ($dateOfBirth !== '' && (!$birthDate || $birthDate->format('Y-m-d') !== $dateOfBirth)) {
        $message = 'Enter a valid date of birth.';
        $messageType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM auth_users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);

            if ($stmt->fetchColumn()) {
                $message = 'Email already registered.';
                $messageType = 'error';
            } else {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO auth_users (name, email, password, role, status) VALUES (?, ?, ?, 'patient', 'active')");
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $userId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO patient_profiles (user_id, mobile_number, address, date_of_birth, emergency_contact_name, emergency_contact_phone, privacy_consent_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$userId, $mobile, $address ?: null, $dateOfBirth ?: null, $emergencyName ?: null, $emergencyPhone ?: null]);
                $pdo->commit();

                $message = 'Registration successful! Please log in.';
                $messageType = 'success';
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = 'Registration failed. Please try again.';
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MinglaDent</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #fffbee;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background-color: #fffbee;
            border: 2px solid #6784c8;
            border-radius: 10px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }

        h1 {
            color: #6784c8;
            font-size: 36px;
            font-weight: 600;
            margin-bottom: 30px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #6784c8;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        input, select {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 2px solid #6784c8;
            border-radius: 5px;
            background-color: #fffbee;
            color: #6784c8;
            font-family: 'Montserrat', sans-serif;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #6784c8;
        }

        button {
            width: 100%;
            padding: 15px;
            background-color: #6784c8;
            color: #fffbee;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        button:hover {
            background-color: #5a6fa8;
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: #6784c8;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .back-link {
            text-align: center;
            margin-top: 15px;
        }

        .back-link a {
            color: #6784c8;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Register</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" maxlength="255" autocomplete="name" required>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" maxlength="255" autocomplete="email" required>
            </div>

            <div class="form-group">
                <label>Mobile number</label>
                <input type="tel" name="mobile_number" maxlength="20" autocomplete="tel" required>
            </div>

            <div class="form-group">
                <label>Date of birth</label>
                <input type="date" name="date_of_birth">
            </div>

            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" maxlength="255" autocomplete="street-address">
            </div>

            <div class="form-group">
                <label>Emergency contact name</label>
                <input type="text" name="emergency_contact_name" maxlength="255">
            </div>

            <div class="form-group">
                <label>Emergency contact number</label>
                <input type="tel" name="emergency_contact_phone" maxlength="20">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" minlength="8" autocomplete="new-password" required>
            </div>
            
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
            </div>
            
            <div class="form-group">
                <label><input type="checkbox" name="privacy_consent" value="1" required> I consent to the collection and use of my information for patient care under the Data Privacy Act of 2012.</label>
            </div>
            
            <button type="submit">Register</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
        <div class="back-link">
            <a href="../index.php">← Back to Home</a>
        </div>
    </div>

</body>
</html>
