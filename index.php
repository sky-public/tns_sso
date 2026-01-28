<?php
session_start();

// Load configuration
require_once 'config.inc.php';

// Generate state parameter for CSRF protection
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));

// Build authorization URL
$auth_params = [
    'client_id' => $config['client_id'],
    'response_type' => 'code',
    'redirect_uri' => $config['redirect_uri'],
    'response_mode' => 'query',
    'scope' => $config['scopes'],
    'state' => $_SESSION['oauth_state']
];

$login_url = $authorize_url . '?' . http_build_query($auth_params);

// Check if welcome screen should be skipped
if (!isset($sso_config['show_welcome_screen']) || $sso_config['show_welcome_screen'] === false) {
    // Redirect directly to Microsoft SSO
    header('Location: ' . $login_url);
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Willkommen - TANSS SSO</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated background elements */
        .bg-animation {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 0;
            overflow: hidden;
        }

        .bg-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite;
        }

        .bg-circle:nth-child(1) {
            width: 300px;
            height: 300px;
            top: -150px;
            left: -150px;
            animation-delay: 0s;
        }

        .bg-circle:nth-child(2) {
            width: 200px;
            height: 200px;
            bottom: -100px;
            right: -100px;
            animation-delay: 5s;
        }

        .bg-circle:nth-child(3) {
            width: 150px;
            height: 150px;
            top: 50%;
            left: 10%;
            animation-delay: 10s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-50px) scale(1.1); }
        }

        .welcome-container {
            background: white;
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            text-align: center;
            position: relative;
            z-index: 1;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-container {
            margin-bottom: 30px;
        }

        .logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .logo svg {
            width: 60px;
            height: 60px;
        }

        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 32px;
            font-weight: 600;
        }

        .subtitle {
            color: #666;
            margin-bottom: 10px;
            font-size: 18px;
            line-height: 1.6;
        }

        .description {
            color: #888;
            margin-bottom: 35px;
            font-size: 14px;
            line-height: 1.5;
        }

        .ms-button {
            background: #2f2f2f;
            color: white;
            border: none;
            padding: 16px 40px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(47, 47, 47, 0.3);
        }

        .ms-button:hover {
            background: #1a1a1a;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(47, 47, 47, 0.4);
        }

        .ms-button:active {
            transform: translateY(0);
        }

        .ms-icon {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        .features {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-around;
            gap: 20px;
        }

        .feature {
            text-align: center;
            flex: 1;
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            margin: 0 auto 10px;
            background: #f0f0ff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 20px;
        }

        .feature-text {
            font-size: 12px;
            color: #666;
            font-weight: 500;
        }

        @media (max-width: 600px) {
            .welcome-container {
                padding: 40px 30px;
            }

            h1 {
                font-size: 26px;
            }

            .features {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="bg-animation">
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
    </div>

    <div class="welcome-container">
        <div class="logo-container">
            <div class="logo">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L2 7V17L12 22L22 17V7L12 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <path d="M12 22V12" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M22 7L12 12L2 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>

        <h1>Willkommen</h1>
        <p class="subtitle">TANSS Single Sign-On</p>
        <p class="description">
            Melden Sie sich mit Ihrem Microsoft 365 Konto an, um auf TANSS zuzugreifen.
        </p>

        <a href="<?php echo htmlspecialchars($login_url); ?>" class="ms-button">
            <svg class="ms-icon" viewBox="0 0 23 23" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 0h11v11H0V0z" fill="#F25022"/>
                <path d="M12 0h11v11H12V0z" fill="#7FBA00"/>
                <path d="M0 12h11v11H0V12z" fill="#00A4EF"/>
                <path d="M12 12h11v11H12V12z" fill="#FFB900"/>
            </svg>
            Mit Microsoft anmelden
        </a>

        <div class="features">
            <div class="feature">
                <div class="feature-icon">🔒</div>
                <div class="feature-text">Sicher</div>
            </div>
            <div class="feature">
                <div class="feature-icon">⚡</div>
                <div class="feature-text">Schnell</div>
            </div>
            <div class="feature">
                <div class="feature-icon">✓</div>
                <div class="feature-text">Einfach</div>
            </div>
        </div>
    </div>
</body>
</html>