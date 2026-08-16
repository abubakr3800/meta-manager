<?php
/**
 * Short Circuit Company — Meta (Facebook/Instagram) Manager
 * Central configuration. Secrets live in /.env (git-ignored on a clean setup).
 */

// ---------------------------------------------------------------------
// Load /.env into $_ENV (Apache/LiteSpeed do not read .env on their own)
// ---------------------------------------------------------------------
(static function (): void {
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
})();

function env(string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    $value = getenv($key);
    return $value === false ? $default : $value;
}

// ---------------------------------------------------------------------
// Local (XAMPP / localhost) vs production (shortcircuit.company)
// ---------------------------------------------------------------------
$httpHost = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
if (str_contains($httpHost, 'shortcircuit.company')) {
    $appIsLocal = false;
} elseif (in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true)) {
    $appIsLocal = true;
} else {
    // CLI / unknown host: Windows = local XAMPP, Linux = the live server
    $appIsLocal = PHP_OS_FAMILY === 'Windows';
}
define('APP_IS_LOCAL', $appIsLocal);

/**
 * URL prefix of the public/ folder, e.g. /meta-manager/public or /public.
 */
function sc_app_base_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === '') {
        return env('SC_APP_BASE_PATH', '/public') ?? '/public';
    }
    $dir = rtrim(dirname($script), '/');
    if (preg_match('#/(oauth|api)$#', $dir)) {
        $dir = dirname($dir);
    }
    if ($dir === '/' || $dir === '.' || $dir === '') {
        return '';
    }
    return $dir;
}
define('APP_BASE_PATH', sc_app_base_path());

function app_path(string $path = ''): string
{
    return rtrim(APP_BASE_PATH, '/') . '/' . ltrim($path, '/');
}

function app_redirect(string $path): never
{
    header('Location: ' . app_path($path));
    exit;
}

// ---------------------------------------------------------------------
// Database — local SC_DB_* vs production REMOTE_DB_*
// ---------------------------------------------------------------------
if (APP_IS_LOCAL) {
    define('DB_HOST', env('SC_DB_HOST', 'localhost'));
    define('DB_NAME', env('SC_DB_NAME', 'sc_meta_manager'));
    define('DB_USER', env('SC_DB_USER', 'root'));
    define('DB_PASS', env('SC_DB_PASS', ''));
} else {
    define('DB_HOST', env('REMOTE_DB_HOST', 'localhost'));
    define('DB_NAME', env('REMOTE_DB_NAME', 'sc_meta_manager'));
    define('DB_USER', env('REMOTE_DB_USER', 'root'));
    define('DB_PASS', env('REMOTE_DB_PASS', ''));
}
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
// Meta / Facebook App credentials
// ---------------------------------------------------------------------
define('META_APP_ID', env('SC_META_APP_ID', ''));
define('META_APP_SECRET', env('SC_META_APP_SECRET', ''));
define('META_GRAPH_VERSION', env('SC_META_GRAPH_VERSION', 'v26.0'));
define('META_LOGIN_CONFIG_ID', env('SC_META_LOGIN_CONFIG_ID', ''));

/**
 * Always match the URL the browser is on. A stale .env redirect (localhost
 * or /public/ without /meta-manager) is what Facebook reports as
 * "domain isn't included in the app's domains".
 */
function sc_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function sc_meta_redirect_uri(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '') {
        $scheme = sc_request_is_https() ? 'https' : 'http';
        return $scheme . '://' . $host . app_path('oauth/callback.php');
    }
    return APP_IS_LOCAL
        ? (env('SC_META_REDIRECT_URI', 'http://localhost:8000/public/oauth/callback.php') ?? '')
        : (env('SC_META_REMOTE_REDIRECT_URI', 'https://shortcircuit.company/meta-manager/public/oauth/callback.php') ?? '');
}
define('META_REDIRECT_URI', sc_meta_redirect_uri());

define('META_SCOPES', implode(',', [
    'pages_show_list',
    'pages_read_engagement',
    'pages_manage_posts',
    'pages_manage_metadata',
    'instagram_basic',
    'instagram_content_publish',
    'ads_management',
    'ads_read',
    'leads_retrieval',
    'business_management',
]));

define('TOKEN_ENC_KEY', env('SC_TOKEN_ENC_KEY', '12345678901234567890123456789012'));

define('APP_NAME', 'Short Circuit — Meta Manager');
define('APP_TIMEZONE', 'Africa/Cairo');
date_default_timezone_set(APP_TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', APP_IS_LOCAL ? '1' : '0');
