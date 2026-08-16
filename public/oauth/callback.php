<?php
require_once __DIR__ . '/../../includes/Session.php';
require_once __DIR__ . '/../../includes/MetaAuth.php';

$adminId = Session::requireLogin();

if (!empty($_GET['error'])) {
    http_response_code(400);
    echo 'Meta authorization was cancelled or denied: ' . htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
    exit;
}

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

if (!$code || !$state || !MetaAuth::consumeState($state, $adminId)) {
    http_response_code(400);
    echo 'Invalid or expired OAuth2 state. Please try connecting again.';
    exit;
}

try {
    MetaAuth::handleCallback($code, $adminId);
    header('Location: /public/index.php?connected=1');
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Failed to complete Meta login: ' . htmlspecialchars($e->getMessage());
}
