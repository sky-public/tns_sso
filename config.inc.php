<?php
/**
 * Microsoft 365 Entra ID SSO Configuration
 * 
 * IMPORTANT: Update these values with your Azure app registration details
 * Never commit this file with actual secrets to version control
 */

// Azure/Entra ID Configuration
$config = [
    // Your Azure AD tenant ID (from Azure Portal > Entra ID > Overview)
    // Use 'common' for multi-tenant, 'organizations', or your specific tenant ID
    'tenant_id' => '',
    
    // Application (client) ID from your app registration
    'client_id' => '',
    
    // Client secret value (from Certificates & secrets)
    'client_secret' => '',
    
    // Redirect URI - must match exactly what's in Azure app registration
    // Update this when deploying to production
    'redirect_uri' => 'https://TANSSURL/sso/callback.php',
    
    // OAuth scopes to request
    'scopes' => 'openid profile email User.Read'
];

// TANSS API Configuration 
$tanss_config = [
    // TANSS API base URL (without trailing slash)
    'api_url' => 'https://TANSSURL',

    // Database API key (optional, if your TANSS instance requires it)
    'dbapikey' => ''
];


// Microsoft Identity Platform Endpoints
$authorize_url = "https://login.microsoftonline.com/{$config['tenant_id']}/oauth2/v2.0/authorize";
$token_url = "https://login.microsoftonline.com/{$config['tenant_id']}/oauth2/v2.0/token";
$graph_url = 'https://graph.microsoft.com/v1.0/me';



// Application Settings
define('APP_NAME', 'M365 SSO App');
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// Load TANSS database credentials from parent config
require_once __DIR__ . '/../config.inc.php';

// TANSS Database Configuration (for direct session creation)
$tanss_db = [
    // Database connection settings from parent config
    'host' => $mySQLhost,
    'user' => $mySQLusername,
    'password' => $mySQLpassword,
    'database' => $mySQLdatabase,

    // Enable debug logging
    'debug' => false
];


// SSO User Mapping Configuration
$sso_config = [
    // Email-to-Username mapping strategy:
    // 'email' - use full email as TANSS username
    // 'prefix' - use email prefix (before @) as TANSS username
    // 'custom' - use custom mapping (define in callback.php)
    'username_mapping' => 'email',

    // Skip password verification for SSO users (recommended for SSO)
    'skip_password_check' => true,

    // If false, SSO will fail if user doesn't exist in TANSS
    'auto_provision_users' => false,

    // Show welcome screen before SSO redirect
    // true = Display graphical welcome screen with login button
    // false = Redirect immediately to Microsoft SSO
    'show_welcome_screen' => true
];

// Environment Check
$is_configured = ($config['tenant_id'] !== 'YOUR_TENANT_ID' &&
                  $config['client_id'] !== 'YOUR_CLIENT_ID' &&
                  $config['client_secret'] !== 'YOUR_CLIENT_SECRET');
?>