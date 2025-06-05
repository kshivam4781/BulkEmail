<?php
session_start();
require_once 'config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Remove debug information from top
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = (new Database())->connect();
    $email = $_POST['email'];
    $password = $_POST['password'];

    try {
        // Debug: Print the received credentials
        error_log("Login attempt - Email: " . $email);
        
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Debug: Check if user was found
        if ($user) {
            error_log("User found in database");
            error_log("Stored hash: " . $user['passwordHash']);
            error_log("Attempting to verify password");
            
            if (password_verify($password, $user['passwordHash'])) {
                error_log("Password verified successfully");
                $_SESSION['user_id'] = $user['userId'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                
                // Debug: Check session
                error_log("Session data after login: " . print_r($_SESSION, true));
                
                // Ensure no output before redirect
                if (!headers_sent()) {
                    header('Location: dashboard.php');
                    exit();
                } else {
                    error_log("Headers already sent. Cannot redirect.");
                    echo "Login successful but redirect failed. <a href='dashboard.php'>Click here to continue</a>";
                }
            } else {
                error_log("Password verification failed");
                $error = "Invalid email or password";
            }
        } else {
            error_log("No user found with email: " . $email);
            $error = "Invalid email or password";
        }
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = "Database error occurred";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sky Bulk Email Sender - Login</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            position: relative;
            overflow: hidden;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradient 3s ease infinite;
        }

        .header p {
            color: #666;
            font-size: 16px;
        }

        .input-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }

        .input-group input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .input-group label {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            top: 0;
            left: 10px;
            font-size: 12px;
            background: white;
            padding: 0 5px;
            color: #667eea;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            user-select: none;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .login-button {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }

        .login-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .cute-element {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 100px;
            height: 100px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .cute-element:hover {
            transform: scale(1.1) rotate(10deg);
        }

        .cloud {
            position: relative;
            width: 100%;
            height: 100%;
            background: white;
            border-radius: 50px;
            animation: float 3s ease-in-out infinite;
        }

        .cloud::before {
            content: '';
            position: absolute;
            top: -20px;
            left: 20px;
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
        }

        .cloud::after {
            content: '';
            position: absolute;
            top: -10px;
            right: 20px;
            width: 30px;
            height: 30px;
            background: white;
            border-radius: 50%;
        }

        .face {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 20px;
        }

        .eyes {
            position: absolute;
            width: 100%;
            height: 10px;
            display: flex;
            justify-content: space-around;
        }

        .eye {
            width: 8px;
            height: 8px;
            background: #333;
            border-radius: 50%;
            animation: blink 3s infinite;
        }

        .mouth {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 10px;
            border-radius: 10px;
            background: #333;
        }

        .speech-bubble {
            position: fixed;
            bottom: 140px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            max-width: 250px;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
            font-size: 14px;
            line-height: 1.4;
            text-align: center;
        }

        .speech-bubble::after {
            content: '';
            position: absolute;
            bottom: -10px;
            right: 20px;
            border-width: 10px 10px 0;
            border-style: solid;
            border-color: white transparent transparent;
        }

        .speech-bubble.show {
            opacity: 1;
            transform: translateY(0);
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
            100% {
                transform: translateY(0px);
            }
        }

        @keyframes blink {
            0%, 96%, 100% {
                height: 8px;
            }
            98% {
                height: 2px;
            }
        }

        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            display: none;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .error-message {
            color: #dc3545;
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }

        pre {
            background: rgba(0, 0, 0, 0.1);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 12px;
            color: #333;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Sky Bulk Email Sender</h1>
            <p>Login to your account</p>
        </div>
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="" autocomplete="off">
            <div class="input-group">
                <input type="email" id="email" name="email" placeholder=" " required autocomplete="off">
                <label for="email">Email Address</label>
            </div>
            <div class="input-group">
                <input type="password" id="password" name="password" placeholder=" " required autocomplete="new-password">
                <label for="password">Password</label>
                <span class="password-toggle" onclick="togglePassword()">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
            <button type="submit" class="login-button">Login</button>
        </form>
    </div>

    <div class="cute-element">
        <div class="cloud">
            <div class="face">
                <div class="eyes">
                    <div class="eye"></div>
                    <div class="eye"></div>
                </div>
                <div class="mouth"></div>
            </div>
        </div>
    </div>

    <div class="speech-bubble" id="speechBubble"></div>

    <script>
        const speechBubble = document.getElementById('speechBubble');
        const messages = [
            "Hi! I'm your cloud friend! ☁️",
            "Need help logging in? Just ask! 😊",
            "The sky's the limit with our email sender! 🌈",
            "Don't worry, I'll keep you company! 💕",
            "Login and let's send some emails! 📧",
            "I love floating around here! 🎈",
            "Did you know clouds are made of tiny water droplets? 💧",
            "I'm here to make your day brighter! ☀️",
            "Let's make some email magic happen! ✨",
            "I'm your personal email assistant! 📬"
        ];

        let currentMessageIndex = 0;

        function showNextMessage() {
            speechBubble.textContent = messages[currentMessageIndex];
            speechBubble.classList.add('show');
            
            setTimeout(() => {
                speechBubble.classList.remove('show');
                currentMessageIndex = (currentMessageIndex + 1) % messages.length;
                
                setTimeout(() => {
                    showNextMessage();
                }, 1000);
            }, 5000);
        }

        // Start showing messages
        setTimeout(() => {
            showNextMessage();
        }, 1000);

        // Clear form fields on page load
        window.onload = function() {
            document.getElementById('email').value = '';
            document.getElementById('password').value = '';
        }

        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Prevent browser autofill
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.setAttribute('autocomplete', 'off');
                input.setAttribute('autocorrect', 'off');
                input.setAttribute('autocapitalize', 'off');
                input.setAttribute('spellcheck', 'false');
            });
        });
    </script>
</body>
</html> 