<?php
session_start();

// Load configuration and utilities
require_once 'config.inc.php';
require_once 'class.mysql.php';

// Error handling
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
    $error_description = htmlspecialchars($_GET['error_description'] ?? 'Unknown error');

    // Log the full error for debugging
    error_log("SSO Error: " . $error);
    error_log("SSO Error Description: " . $error_description);
    error_log("SSO Full GET params: " . print_r($_GET, true));

    if (strpos($error_description, 'AADSTS') !== false) {
        displayError("Application Registration Required",
            "This application is not properly registered in your Microsoft 365 Entra ID tenant.",
            $error_description);
        exit;
    }

    displayError("Authentication Error", $error_description, $error);
    exit;
}

// Verify state parameter (CSRF protection)
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    displayError("Security Error", "Invalid state parameter. Possible CSRF attack detected.", "STATE_MISMATCH");
    exit;
}

// Get authorization code
if (!isset($_GET['code'])) {
    displayError("Authentication Error", "No authorization code received.", "NO_CODE");
    exit;
}

$code = $_GET['code'];

// Exchange authorization code for access token
$token_params = [
    'client_id' => $config['client_id'],
    'client_secret' => $config['client_secret'],
    'code' => $code,
    'redirect_uri' => $config['redirect_uri'],
    'grant_type' => 'authorization_code',
    'scope' => 'openid profile email User.Read'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
// Security: Enable SSL/TLS verification
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    $error_data = json_decode($response, true);
    displayError("Token Exchange Failed",
        $error_data['error_description'] ?? 'Failed to obtain access token',
        $error_data['error'] ?? 'TOKEN_ERROR');
    exit;
}

$token_data = json_decode($response, true);

if (!isset($token_data['access_token'])) {
    displayError("Authentication Error", "No access token received.", "NO_TOKEN");
    exit;
}

// Get user information from Microsoft Graph
$ch = curl_init($graph_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token_data['access_token'],
    'Content-Type: application/json'
]);
// Security: Enable SSL/TLS verification
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$user_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    displayError("User Info Error", "Failed to retrieve user information from Microsoft Graph.", "GRAPH_ERROR");
    exit;
}

$user_data = json_decode($user_response, true);
$ms_email = $user_data['mail'] ?? $user_data['userPrincipalName'];

// ============================================================================
// TANSS AUTHENTICATION WITH DYNAMIC PASSWORD RESET
// ============================================================================

try {
    // Connect to TANSS database
    $db = new db(
        $tanss_db['host'],
        $tanss_db['user'],
        $tanss_db['password'],
        $tanss_db['database'],
        'sso',
        'utf8',
        $tanss_db['debug'] ? 1 : 0
    );

    // Find employee in TANSS by email (SSO matches on email field only)
    $employee = $db->query(
        'SELECT * FROM mitarbeiter WHERE email = ? AND aktiv = \'Y\' LIMIT 1',
        $ms_email
    )->fetchRow();

    if (!$employee) {
        displayError(
            "TANSS User Not Found",
            "Successfully authenticated with Microsoft 365, but your account was not found in TANSS. " .
            "Email tried: " . htmlspecialchars($ms_email) . ". " .
            "Please contact your system administrator.",
            "TANSS_USER_NOT_FOUND"
        );
        exit;
    }

    // Check if login field is empty and create temporary login if needed
    $temporary_login = null;
    $original_login = $employee['login'];

    if (empty($employee['login'])) {
        // Create temporary random login
        $temporary_login = generateSecurePassword(32);

        $db->query(
            'UPDATE mitarbeiter SET login = ? WHERE ID = ?',
            $temporary_login,
            $employee['ID']
        );

        // Use temporary login for authentication
        $tanss_identifier = $temporary_login;

        if ($tanss_db['debug']) {
            error_log("SSO: Created temporary login for employee ID: {$employee['ID']}");
        }
    } else {
        // Use existing login
        $tanss_identifier = $employee['login'];
    }

    // Cache the original password before changing it
    $original_password = $employee['password'];

    // Generate a new secure random password
    $new_password = generateSecurePassword(32);

    // Hash the password using BCrypt (TANSS standard with salt)
    $password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Update password in TANSS database
    $db->query(
        'UPDATE mitarbeiter SET password = ? WHERE ID = ?',
        $password_hash,
        $employee['ID']
    );

    if ($tanss_db['debug']) {
        error_log("SSO: Updated password for employee ID: {$employee['ID']}");
        error_log("SSO: Managing TOTP token...");
    }

    // Get or create TOTP token entry
    $totp_data = $db->query(
        'SELECT google_auth FROM mitarbeiter_token WHERE maID = ? LIMIT 1',
        $employee['ID']
    )->fetchRow();

    $totp_secret = null;

    if (!$totp_data) {
        // No row exists - create one with a new TOTP secret
        $totp_secret = generateTOTPSecret();

        $db->query(
            'INSERT INTO mitarbeiter_token (maID, google_auth, ext_erforderlich) VALUES (?, ?, 1)',
            $employee['ID'],
            $totp_secret
        );

        if ($tanss_db['debug']) {
            error_log("SSO: Created mitarbeiter_token row with new TOTP secret for employee ID: {$employee['ID']}");
        }
    } else if (empty($totp_data['google_auth'])) {
        // Row exists but no secret - create and update
        $totp_secret = generateTOTPSecret();

        $db->query(
            'UPDATE mitarbeiter_token SET google_auth = ?, ext_erforderlich = 1 WHERE maID = ?',
            $totp_secret,
            $employee['ID']
        );

        if ($tanss_db['debug']) {
            error_log("SSO: Updated mitarbeiter_token with new TOTP secret for employee ID: {$employee['ID']}");
        }
    } else {
        // Secret exists - use it and ensure ext_erforderlich is set
        $totp_secret = $totp_data['google_auth'];

        $db->query(
            'UPDATE mitarbeiter_token SET ext_erforderlich = 1 WHERE maID = ?',
            $employee['ID']
        );

        if ($tanss_db['debug']) {
            error_log("SSO: Using existing TOTP secret for employee ID: {$employee['ID']}");
        }
    }

    // Generate TOTP token
    $totp_token = generateTOTP($totp_secret);

    if ($tanss_db['debug']) {
        error_log("SSO: Generated TOTP token for employee ID: {$employee['ID']}");
    }

    // Now authenticate with TANSS by POST to login endpoint
	$login_url = $tanss_config['api_url'].'/index.php?section=login';

    $login_payload = [
        'username' => $tanss_identifier,
        'password' => $new_password,
        'token' => $totp_token
    ];

    $ch = curl_init($login_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($login_payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    // Security: Enable SSL/TLS verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    // Important: Capture cookies from TANSS login
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, ''); // Enable cookie handling
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($tanss_db['debug']) {
        error_log("SSO: TANSS login response code: $http_code");
        error_log("SSO: TANSS login response: $response");
    }

    curl_close($ch);

    if ($http_code !== 200) {
        displayError(
            "TANSS Login Failed",
            "Password was reset but login to TANSS failed. HTTP Code: $http_code",
            "TANSS_LOGIN_FAILED"
        );
        exit;
    }

    // Parse response to get session cookies
    list($headers, $body) = explode("\r\n\r\n", $response, 2);

    // Extract Set-Cookie headers with full cookie attributes
    preg_match_all('/^Set-Cookie:\s*(.+)$/mi', $headers, $cookies);

    if ($tanss_db['debug']) {
        error_log("SSO: Captured cookies: " . print_r($cookies[1], true));
    }

    // Find and validate tanss_sid cookie
    $tanss_sid_found = false;
    foreach ($cookies[1] as $cookie) {
        if (stripos($cookie, 'tanss_sid=') === 0) {
            $tanss_sid_found = true;
            break;
        }
    }

    if (!$tanss_sid_found && $tanss_db['debug']) {
        error_log("SSO: Warning - tanss_sid cookie not found in response");
    }

    // Parse response - TANSS returns {"ok":true} on successful login
    if ($tanss_db['debug']) {
        error_log("SSO: TANSS login response body: " . $body);
    }

    // Try to parse as JSON
    $login_result = json_decode($body, true);

    

    if (!$login_result || !isset($login_result['ok'])) {
        // Check if response is plain text "ok"
        $trimmed_body = trim($body);
        if ($trimmed_body === 'ok') {
            $login_result = ['ok' => true];
        } else {
            displayError(
                "TANSS Authentication Failed",
                "Login request succeeded but response was invalid: " . htmlspecialchars($trimmed_body),
                "TANSS_INVALID_RESPONSE"
            );
            exit;
        }
    }

    // Check if login was successful
    if (!$login_result['ok']) {
        displayError(
            "TANSS Authentication Failed",
            "TANSS rejected the login credentials.",
            "TANSS_LOGIN_REJECTED"
        );
        exit;
    }

    // Restore original password after successful login
    $db->query(
        'UPDATE mitarbeiter SET password = ? WHERE ID = ?',
        $original_password,
        $employee['ID']
    );

    if ($tanss_db['debug']) {
        error_log("SSO: Restored original password for employee ID: {$employee['ID']}");
    }

    // Delete temporary login if one was created
    if ($temporary_login !== null) {
        $db->query(
            'UPDATE mitarbeiter SET login = ? WHERE ID = ?',
            $original_login,
            $employee['ID']
        );

        if ($tanss_db['debug']) {
            error_log("SSO: Deleted temporary login for employee ID: {$employee['ID']}");
        }
    }

    // Check if user has LDAP entry and enable login temporarily
    $ldap_entry = $db->query(
        'SELECT loginaktiviert FROM mitarbeiter_ldap WHERE mitarbeiterID = ? LIMIT 1',
        $employee['ID']
    )->fetchRow();

    $ldap_login_was_disabled = false;
    if ($ldap_entry) {
        if ($ldap_entry['loginaktiviert'] != 1) {
            // Enable login temporarily
            $db->query(
                'UPDATE mitarbeiter_ldap SET loginaktiviert = 1 WHERE mitarbeiterID = ?',
                $employee['ID']
            );
            $ldap_login_was_disabled = true;

            if ($tanss_db['debug']) {
                error_log("SSO: Temporarily enabled LDAP login for employee ID: {$employee['ID']}");
            }
        }
    }

    // Security: Regenerate session ID to prevent session fixation (Issue #9)
    session_regenerate_id(true);

    // Restore LDAP login state if it was disabled
    if ($ldap_login_was_disabled) {
        $db->query(
            'UPDATE mitarbeiter_ldap SET loginaktiviert = 0 WHERE mitarbeiterID = ?',
            $employee['ID']
        );

        if ($tanss_db['debug']) {
            error_log("SSO: Restored LDAP login to disabled for employee ID: {$employee['ID']}");
        }
    }

    // Forward TANSS cookies to browser (especially tanss_sid)
    if (!empty($cookies[1])) {
        foreach ($cookies[1] as $cookie_header) {
            // Set each cookie in the browser with all attributes preserved
            header("Set-Cookie: $cookie_header", false);

            if ($tanss_db['debug']) {
                error_log("SSO: Setting cookie in browser: " . $cookie_header);
            }
        }
    } else {
        if ($tanss_db['debug']) {
            error_log("SSO: Warning - No cookies to forward to browser");
        }
    }

    // Redirect to main application
    header('Location: ../index.php');
    exit;

} catch (Exception $e) {
    displayError(
        "SSO Authentication Failed",
        "An error occurred during authentication: " . htmlspecialchars($e->getMessage()),
        "SSO_ERROR"
    );
    exit;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Generate a secure random password
 */
function generateSecurePassword($length = 32) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+[]{}';
    $password = '';
    $chars_length = strlen($chars);

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $chars_length - 1)];
    }

    return $password;
}

/**
 * Generate a new TOTP secret (Base32 encoded)
 *
 * @param int $length Length of the secret in bytes (default 20 for compatibility)
 * @return string Base32 encoded TOTP secret
 */
function generateTOTPSecret($length = 20) {
    // Generate random bytes
    $bytes = random_bytes($length);

    // Encode as Base32
    return base32_encode($bytes);
}

/**
 * Encode binary data to Base32
 *
 * @param string $input Binary data
 * @return string Base32 encoded string
 */
function base32_encode($input) {
    // Base32 alphabet (RFC 4648)
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    $output = '';
    $buffer = 0;
    $bitsLeft = 0;

    for ($i = 0; $i < strlen($input); $i++) {
        $buffer = ($buffer << 8) | ord($input[$i]);
        $bitsLeft += 8;

        while ($bitsLeft >= 5) {
            $bitsLeft -= 5;
            $output .= $alphabet[($buffer >> $bitsLeft) & 0x1F];
        }
    }

    // Handle remaining bits
    if ($bitsLeft > 0) {
        $output .= $alphabet[($buffer << (5 - $bitsLeft)) & 0x1F];
    }

    // Add padding
    $padding = (8 - (strlen($output) % 8)) % 8;
    $output .= str_repeat('=', $padding);

    return $output;
}

/**
 * Generate TOTP (Time-based One-Time Password) from secret
 * Implements RFC 6238 TOTP algorithm
 *
 * @param string $secret Base32 encoded secret from google_auth column
 * @return string 6-digit TOTP code
 */
function generateTOTP($secret) {
    // Decode Base32 secret
    $secret = base32_decode($secret);

    // Get current time step (30 seconds)
    $time = floor(time() / 30);

    // Convert time to 8-byte binary string (big-endian)
    $time_bytes = pack('J', $time);

    // Generate HMAC-SHA1 hash
    $hash = hash_hmac('sha1', $time_bytes, $secret, true);

    // Dynamic truncation (RFC 4226)
    $offset = ord($hash[19]) & 0xf;
    $code = (
        ((ord($hash[$offset]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    );

    // Generate 6-digit code
    $code = $code % 1000000;

    // Pad with leading zeros
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

/**
 * Decode Base32 string to binary
 *
 * @param string $input Base32 encoded string
 * @return string Binary data
 */
function base32_decode($input) {
    // Base32 alphabet (RFC 4648)
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // Remove padding and convert to uppercase
    $input = strtoupper(str_replace('=', '', $input));

    $output = '';
    $buffer = 0;
    $bitsLeft = 0;

    for ($i = 0; $i < strlen($input); $i++) {
        $char = $input[$i];
        $value = strpos($alphabet, $char);

        if ($value === false) {
            continue; // Skip invalid characters
        }

        $buffer = ($buffer << 5) | $value;
        $bitsLeft += 5;

        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }

    return $output;
}

function displayError($title, $message, $error_code) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authentication Error</title>
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
            }

            .error-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                max-width: 500px;
                width: 100%;
            }

            .error-icon {
                width: 60px;
                height: 60px;
                background: #ff4444;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                color: white;
                font-size: 30px;
            }

            h1 {
                color: #333;
                margin-bottom: 15px;
                text-align: center;
                font-size: 24px;
            }

            p {
                color: #666;
                line-height: 1.6;
                margin-bottom: 20px;
                text-align: center;
            }

            .error-code {
                background: #f5f5f5;
                padding: 10px;
                border-radius: 5px;
                font-family: monospace;
                font-size: 12px;
                color: #d32f2f;
                margin-bottom: 20px;
                word-break: break-all;
            }

            .button-group {
                display: flex;
                gap: 10px;
                justify-content: center;
            }

            .btn {
                padding: 10px 20px;
                border-radius: 5px;
                text-decoration: none;
                font-size: 14px;
                transition: all 0.3s;
                border: none;
                cursor: pointer;
            }

            .btn-primary {
                background: #667eea;
                color: white;
            }

            .btn-primary:hover {
                background: #5568d3;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠</div>
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <div class="error-code">Error Code: <?php echo htmlspecialchars($error_code); ?></div>
            <div class="button-group">
                <a href="index.php" class="btn btn-primary">Try Again</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
