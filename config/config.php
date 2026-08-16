<?php
/**
 * Short Circuit Company — Meta (Facebook/Instagram) Manager
 * Central configuration. Copy this file to config.local.php (git-ignored)
 * for real secrets — never commit real App Secrets or DB passwords.
 */

// ---------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------
define('DB_HOST', getenv('SC_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('SC_DB_NAME') ?: 'sc_meta_manager');
define('DB_USER', getenv('SC_DB_USER') ?: 'root');
define('DB_PASS', getenv('SC_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
// Meta / Facebook App credentials
// Create an app at https://developers.facebook.com/apps
// Required products: Facebook Login, Pages API, Instagram Graph API,
// Marketing API, Webhooks (for Lead Ads, optional).
// ---------------------------------------------------------------------
define('META_APP_ID', getenv('SC_META_APP_ID') ?: '1366651904982181');
define('META_APP_SECRET', getenv('SC_META_APP_SECRET') ?: 'bc37d19a5a25da6d47bdabf6364ce54b');
define('META_GRAPH_VERSION', 'v21.0');
define('META_REDIRECT_URI', getenv('SC_META_REDIRECT_URI') ?: 'http://localhost:8000/public/oauth/callback.php');

// Scopes requested during OAuth2. Trim to what you actually need.
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

// ---------------------------------------------------------------------
// Token encryption (AES-256-GCM). Generate with:
//   php -r "echo bin2hex(random_bytes(32));"
// ---------------------------------------------------------------------
define('TOKEN_ENC_KEY', getenv('SC_TOKEN_ENC_KEY') ?: '12345678901234567890123456789012');

// ---------------------------------------------------------------------
// Session / app
// ---------------------------------------------------------------------
define('APP_NAME', 'Short Circuit — Meta Manager');
define('APP_TIMEZONE', 'Africa/Cairo');
date_default_timezone_set(APP_TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', getenv('SC_APP_ENV') === 'production' ? '0' : '1');
