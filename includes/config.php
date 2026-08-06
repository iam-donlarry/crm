<?php
// Load .env variables (Search up to two levels up for security)
$searchPaths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env',
    __DIR__ . '/../../../.env'
];

foreach ($searchPaths as $path) {
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $_ENV[trim($name)] = trim($value);
                putenv(trim($name) . "=" . trim($value));
            }
        }
        break;
    }
}

// Application Configuration
define('APP_NAME', 'BBSL CRM');

// Dynamic BASE_URL Auto-Detection (Supports both localhost/crm subfolder and production crm.bayebusinesssolutions.com root subdomain)
$envAppUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '');
if (!empty($envAppUrl)) {
    define('BASE_URL', rtrim($envAppUrl, '/'));
} else {
    $protocol = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Calculate root relative directory path
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $appDir  = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    
    $scriptDir = '';
    if (!empty($docRoot) && strtolower(substr($appDir, 0, strlen($docRoot))) === strtolower($docRoot)) {
        $scriptDir = substr($appDir, strlen($docRoot));
        $scriptDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
    } else {
        // Fallback: strip known subfolders from SCRIPT_NAME
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $appRoot = preg_replace('#/(modules|ajax|api|views|includes)/.*$#i', '', $scriptName);
        $scriptDir = rtrim(str_replace('\\', '/', dirname($appRoot)), '/');
    }
    if ($scriptDir === '.' || $scriptDir === '/') $scriptDir = '';
    
    define('BASE_URL', $protocol . '://' . $host . $scriptDir);
}

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Timezone
date_default_timezone_set('UTC');

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'crm');

// Email Configuration (Microsoft 365 OAuth2 / Modern Auth)
define('MAIL_AUTH_MODE', 'XOAUTH2');
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_USER', 'no-reply@bbsl365.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');

// Azure App Credentials (MANDATORY for XOAUTH2)
define('MAIL_TENANT_ID', $_ENV['MAIL_TENANT_ID'] ?? getenv('MAIL_TENANT_ID') ?? '');
define('MAIL_CLIENT_ID', $_ENV['MAIL_CLIENT_ID'] ?? getenv('MAIL_CLIENT_ID') ?? '');
define('MAIL_CLIENT_SECRET', $_ENV['MAIL_CLIENT_SECRET'] ?? getenv('MAIL_CLIENT_SECRET') ?? '');

define('SMTP_FROM_EMAIL', 'no-reply@bbsl365.com');
define('SMTP_FROM_NAME', 'BBSL CRM');
define('MAIL_ENABLED', true);

// Database Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
