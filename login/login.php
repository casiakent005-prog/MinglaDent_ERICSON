<?php
require_once '../database/config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $message = 'Email and password are required';
        $messageType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM auth_users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['staff_role'] = null;
                $_SESSION['clinic_id'] = $user['clinic_id'] ?? null;

                // Redirect based on role
                if ($user['role'] === 'clinic') {
                    $staffStmt = $pdo->prepare("SELECT role, clinic_id FROM clinic_staff WHERE user_id = ? LIMIT 1");
                    $staffStmt->execute([$user['id']]);
                    $staffAssignment = $staffStmt->fetch();

                    if ($staffAssignment) {
                        $_SESSION['staff_role'] = $staffAssignment['role'];
                        $_SESSION['clinic_id'] = $staffAssignment['clinic_id'];

                        if ($staffAssignment['role'] === 'dentist') {
                            header("Location: ../clinic_dentist/dashboard.php");
                        } elseif (in_array($staffAssignment['role'], ['assistant', 'staff'], true)) {
                            header("Location: ../clinic_assistant/dashboard.php");
                        } else {
                            header("Location: ../clinic_admin/dashboard.php");
                        }
                    } else {
                        header("Location: ../clinic_admin/profile.php");
                    }
                } else {
                    if ($user['role'] === 'patient') {
                        header('Location: ../index.php');
                    } elseif ($user['role'] === 'admin') {
                        header('Location: ../system_admin/dashboard.php');
                    } else {
                        header('Location: ../index.php');
                    }
                }
                exit;
            } else {
                $message = 'Invalid email or password';
                $messageType = 'error';
            }
        } catch(PDOException $e) {
            $message = 'Login failed. Please try again.';
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
    <title>Login - MinglaDent</title>
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

        input {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 2px solid #6784c8;
            border-radius: 5px;
            background-color: #fffbee;
            color: #6784c8;
            font-family: 'Montserrat', sans-serif;
        }

        input:focus {
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

        .register-link {
            text-align: center;
            margin-top: 20px;
        }

        .register-link a {
            color: #6784c8;
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Login</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>
</html>
