<?php
require_once '../database/config.php';

$message = '';
$messageType = '';
$token = $_GET['token'] ?? '';

// Validate token
if (empty($token)) {
    $message = 'Invalid or expired reset link.';
    $messageType = 'error';
} else {
    try {
        $stmt = $pdo->prepare("SELECT prt.user_id, u.email FROM password_reset_tokens prt JOIN auth_users u ON prt.user_id = u.id WHERE prt.token = ? AND prt.expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $resetData = $stmt->fetch();

        if (!$resetData) {
            $message = 'Invalid or expired reset link. Please request a new password reset.';
            $messageType = 'error';
        }
    } catch (PDOException $e) {
        $message = 'An error occurred. Please try again.';
        $messageType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirmPassword)) {
        $message = 'Please enter both password fields.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            // Update password
            $stmt = $pdo->prepare("UPDATE auth_users SET password = ? WHERE id = ?");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $resetData['user_id']]);

            // Delete the used token
            $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
            $stmt->execute([$token]);

            $pdo->commit();

            $message = 'Password has been successfully reset. You can now login with your new password.';
            $messageType = 'success';

            // Redirect to login after 3 seconds
            header("refresh:3;url=login_new.php");
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = 'An error occurred. Please try again.';
            $messageType = 'error';
            error_log("Reset password error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MinglaDent</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
        }

        .reset-container {
            display: flex;
            width: 100%;
            max-width: 1200px;
            min-height: 100vh;
            background: #fff;
            position: relative;
        }

        /* Left Panel - Form */
        .reset-form-panel {
            flex: 1;
            padding: 60px 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #fff;
            position: relative;
            z-index: 2;
        }

        /* Right Panel - Image */
        .reset-image-panel {
            flex: 1;
            background: url('https://images.unsplash.com/photo-1629909613654-28e377c37b09?w=800&q=80') center/cover no-repeat;
            position: relative;
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            padding: 40px;
        }

        .reset-image-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(103, 132, 200, 0.3) 0%, rgba(64, 224, 208, 0.3) 100%);
        }

        .brand-logo {
            position: relative;
            z-index: 2;
            color: #fff;
            font-size: 28px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        /* Curved Background Shapes */
        .bg-shape {
            position: absolute;
            border-radius: 50%;
            z-index: 1;
        }

        .bg-shape-1 {
            width: 600px;
            height: 600px;
            background: linear-gradient(135deg, #6784c8 0%, #5a6fa8 100%);
            top: -200px;
            right: -200px;
            opacity: 0.1;
        }

        .bg-shape-2 {
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, #40e0d0 0%, #48d1cc 100%);
            bottom: -150px;
            left: -150px;
            opacity: 0.15;
        }

        .reset-title {
            color: #6784c8;
            font-size: 42px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .reset-subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            color: #333;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6784c8;
            font-size: 18px;
        }

        .form-control {
            width: 100%;
            padding: 14px 45px;
            font-size: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: #fff;
            color: #333;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #6784c8;
            box-shadow: 0 0 0 3px rgba(103, 132, 200, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6784c8;
            font-size: 18px;
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background-color 0.3s ease;
        }

        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }

        .strength-text {
            font-size: 12px;
            margin-top: 4px;
            color: #666;
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #6784c8 0%, #5a6fa8 100%);
            color: #fff;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(103, 132, 200, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(103, 132, 200, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .back-link {
            text-align: center;
            margin-top: 30px;
        }

        .back-link a {
            color: #6784c8;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            font-size: 14px;
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

        /* Responsive Design */
        @media (max-width: 968px) {
            .reset-container {
                flex-direction: column;
            }

            .reset-image-panel {
                display: none;
            }

            .reset-form-panel {
                padding: 40px 30px;
            }
        }

        @media (max-width: 480px) {
            .reset-form-panel {
                padding: 30px 20px;
            }

            .reset-title {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <!-- Background Shapes -->
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <!-- Left Panel - Form -->
        <div class="reset-form-panel">
            <h1 class="reset-title">Reset Password</h1>
            <p class="reset-subtitle">Enter your new password below.</p>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($message) || $messageType === 'error'): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Enter new password" required minlength="8" autocomplete="new-password">
                            <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <p class="strength-text" id="strengthText"></p>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Confirm new password" required minlength="8" autocomplete="new-password">
                            <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">Reset Password</button>
                </form>
            <?php endif; ?>

            <div class="back-link">
                <a href="login_new.php">← Back to Login</a>
            </div>
        </div>

        <!-- Right Panel - Image -->
        <div class="reset-image-panel">
            <div class="brand-logo">MinglaDent</div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPassword = document.getElementById('confirmPassword');
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;

            strengthBar.style.width = (strength * 25) + '%';

            if (strength <= 1) {
                strengthBar.className = 'password-strength-bar strength-weak';
                strengthText.textContent = 'Weak';
                strengthText.style.color = '#dc3545';
            } else if (strength <= 2) {
                strengthBar.className = 'password-strength-bar strength-medium';
                strengthText.textContent = 'Medium';
                strengthText.style.color = '#ffc107';
            } else {
                strengthBar.className = 'password-strength-bar strength-strong';
                strengthText.textContent = 'Strong';
                strengthText.style.color = '#28a745';
            }

            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.textContent = '';
            }
        });
    </script>
</body>
</html>
