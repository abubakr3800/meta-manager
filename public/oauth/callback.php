<?php
require_once __DIR__ . '/../../includes/Session.php';
require_once __DIR__ . '/../../includes/MetaAuth.php';

$adminId = Session::requireLogin();

$fbError = $_GET['error_message'] ?? $_GET['error_description'] ?? $_GET['error'] ?? '';
$fbCode  = $_GET['error_code'] ?? '';
if ($fbError !== '' || $fbCode !== '') {
    $msg = $fbError !== '' ? $fbError : ('Facebook error ' . $fbCode);
    if ($fbCode !== '') {
        $msg .= ' (code ' . $fbCode . ')';
    }
    $msg .= ' Add shortcircuit.company to Settings → Basic → App Domains on app ' . META_APP_ID . '.';
    app_redirect('index.php?meta_error=' . rawurlencode($msg));
}

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

if (!$code || !$state || !MetaAuth::consumeState($state, $adminId)) {
    app_redirect('index.php?meta_error=' . rawurlencode('Meta login did not return a valid code. Try Connect Meta Account again.'));
}

try {
    MetaAuth::handleCallback($code, $adminId);
    app_redirect('index.php?connected=1');
} catch (Throwable $e) {
    app_redirect('index.php?meta_error=' . rawurlencode($e->getMessage()));
}
