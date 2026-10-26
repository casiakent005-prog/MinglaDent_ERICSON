<?php require __DIR__ . '/../includes/login_handler.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MinglaDent</title>
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

        .login-container {
            display: flex;
            width: 100%;
            max-width: 1200px;
            min-height: 100vh;
            background: #fff;
            position: relative;
        }

        /* Left Panel - Login Form */
        .login-form-panel {
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
        .login-image-panel {
            flex: 1;
            background: url('https://images.unsplash.com/photo-1629909613654-28e377c37b09?w=800&q=80') center/cover no-repeat;
            position: relative;
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            padding: 40px;
        }

        .login-image-panel::before {
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

        .login-title {
            color: #6784c8;
            font-size: 42px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .login-subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 40px;
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
            padding: 14px 45px 14px 45px;
            font-size: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: #fff;
            color: #333;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
        }

        .form-control.password-input {
            padding-right: 45px;
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

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .remember-me label {
            font-size: 14px;
            color: #666;
            margin: 0;
            cursor: pointer;
        }

        .forgot-password {
            color: #6784c8;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .btn-login {
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

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(103, 132, 200, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .dental-welcome-section {
            margin-top: 40px;
            padding: 30px;
            background: linear-gradient(135deg, rgba(103, 132, 200, 0.08) 0%, rgba(64, 224, 208, 0.08) 100%);
            border-radius: 12px;
            border: 1px solid rgba(103, 132, 200, 0.15);
            text-align: center;
        }

        .dental-icon {
            font-size: 48px;
            color: #6784c8;
            margin-bottom: 15px;
        }

        .dental-welcome-title {
            color: #6784c8;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .dental-welcome-text {
            color: #666;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .dental-features {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .dental-feature {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #6784c8;
            font-weight: 500;
        }

        .dental-feature i {
            font-size: 14px;
        }

        .create-account {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }

        .create-account a {
            color: #6784c8;
            text-decoration: none;
            font-weight: 600;
        }

        .create-account a:hover {
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

        .back-link {
            text-align: center;
            margin-top: 20px;
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

        /* Responsive Design */
        @media (max-width: 968px) {
            .login-container {
                flex-direction: column;
            }

            .login-image-panel {
                display: none;
            }

            .login-form-panel {
                padding: 40px 30px;
            }
        }

        @media (max-width: 480px) {
            .login-form-panel {
                padding: 30px 20px;
            }

            .login-title {
                font-size: 32px;
            }

            .dental-welcome-section {
                padding: 20px;
            }

            .dental-features {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Background Shapes -->
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <!-- Left Panel - Login Form -->
        <div class="login-form-panel">
            <h1 class="login-title">Sign in to MinglaDent</h1>
            <p class="login-subtitle">Enter your details below</p>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo authEscape(authCsrfToken()); ?>">
                <div class="form-group">
                    <label for="email">Email or username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="text" id="email" name="email" class="form-control" placeholder="Enter your email or username" required autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" class="form-control password-input" placeholder="Enter your password" required autocomplete="current-password">
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                </div>

                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-login">Log in</button>
            </form>

            <div class="dental-welcome-section">
                <i class="fas fa-tooth dental-icon"></i>
                <h3 class="dental-welcome-title">Your Smile, Our Priority</h3>
                <p class="dental-welcome-text">
                    Welcome to MinglaDent, your trusted dental care management system. Sign in to manage your dental records and appointments with ease.
                </p>
                <div class="dental-features">
                    <div class="dental-feature">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure Access</span>
                    </div>
                    <div class="dental-feature">
                        <i class="fas fa-tasks"></i>
                        <span>Easy Management</span>
                    </div>
                    <div class="dental-feature">
                        <i class="fas fa-heart"></i>
                        <span>Better Dental Care</span>
                    </div>
                </div>
            </div>

            <p class="create-account">
                Don't have an account? <a href="register_new.php">Create new account</a>
            </p>

            <div class="back-link">
                <a href="../index.php">← Back to Home</a>
            </div>
        </div>

        <!-- Right Panel - Image -->
        <div class="login-image-panel">
            <div class="brand-logo">MinglaDent</div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
