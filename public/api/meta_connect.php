<?php
require_once __DIR__ . '/../../includes/Session.php';
require_once __DIR__ . '/../../includes/MetaAuth.php';

header('Content-Type: application/json; charset=utf-8');

$adminId = Session::currentUserId();
if (!$adminId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = [];
}

try {
    if (!empty($body['accessToken'])) {
        MetaAuth::handleJsAccessToken($adminId, (string)$body['accessToken']);
    } elseif (!empty($body['code'])) {
        MetaAuth::handleJsCode($adminId, (string)$body['code']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing accessToken or code']);
        exit;
    }
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
