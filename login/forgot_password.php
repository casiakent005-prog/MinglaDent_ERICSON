<?php
require_once '../database/config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $message = 'Please enter your email address.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        try {
            // Check if email exists in the database
            $stmt = $pdo->prepare("SELECT id, name FROM auth_users WHERE email = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Always show success message to prevent email enumeration
            $message = 'If an account with this email exists, a password reset link has been sent.';
            $messageType = 'success';

            if ($user) {
                // Generate a secure token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Delete any existing tokens for this user
                $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
                $stmt->execute([$user['id']]);

                // Insert new token
                $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $token, $expires]);

                // In a real application, you would send an email here
                // For now, we'll log the reset link
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                error_log("Password reset link for {$email}: {$resetLink}");

                // You can uncomment this for testing purposes:
                // $message .= " <br><small>Reset link (for testing): <a href='{$resetLink}'>{$resetLink}</a></small>";
            }
        } catch (PDOException $e) {
            $message = 'An error occurred. Please try again.';
            $messageType = 'error';
            error_log("Forgot password error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MinglaDent</title>
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

        .forgot-container {
            display: flex;
            width: 100%;
            max-width: 1200px;
            min-height: 100vh;
            background: #fff;
            position: relative;
        }

        /* Left Panel - Form */
        .forgot-form-panel {
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
        .forgot-image-panel {
            flex: 1;
            background: url('https://images.unsplash.com/photo-1629909613654-28e377c37b09?w=800&q=80') center/cover no-repeat;
            position: relative;
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            padding: 40px;
        }

        .forgot-image-panel::before {
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

        .forgot-title {
            color: #6784c8;
            font-size: 42px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .forgot-subtitle {
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
            .forgot-container {
                flex-direction: column;
            }

            .forgot-image-panel {
                display: none;
            }

            .forgot-form-panel {
                padding: 40px 30px;
            }
        }

        @media (max-width: 480px) {
            .forgot-form-panel {
                padding: 30px 20px;
            }

            .forgot-title {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <!-- Background Shapes -->
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <!-- Left Panel - Form -->
        <div class="forgot-form-panel">
            <h1 class="forgot-title">Forgot Password?</h1>
            <p class="forgot-subtitle">Enter your email address and we'll send you a link to reset your password.</p>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required autocomplete="email">
                    </div>
                </div>

                <button type="submit" class="btn-submit">Send Reset Link</button>
            </form>

            <div class="back-link">
                <a href="login_new.php">← Back to Login</a>
            </div>
        </div>

        <!-- Right Panel - Image -->
        <div class="forgot-image-panel">
            <div class="brand-logo">MinglaDent</div>
        </div>
    </div>
</body>
</html>
