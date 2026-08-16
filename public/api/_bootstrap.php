<?php
require_once __DIR__ . '/../../includes/Session.php';
require_once __DIR__ . '/../../includes/MetaAPI.php';

header('Content-Type: application/json; charset=utf-8');

$adminId = Session::currentUserId();
if (!$adminId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$identityId = Session::currentIdentityId();

function json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function respond_error(Throwable $e, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

$api = new MetaAPI();
