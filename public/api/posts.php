<?php
require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $pageId = (int)($_GET['page_id'] ?? 0);
            if (!$pageId) {
                respond(['error' => 'page_id is required'], 422);
            }
            respond(['posts' => $api->listPosts($pageId)]);
            break;

        case 'POST':
            $body = json_body();
            $pageId = (int)($body['page_id'] ?? 0);
            $message = trim($body['message'] ?? '');
            if (!$pageId || $message === '') {
                respond(['error' => 'page_id and message are required'], 422);
            }
            $result = $api->createPost(
                $pageId,
                $message,
                $body['link'] ?? null,
                $body['image_url'] ?? null,
                $body['scheduled_at'] ?? null
            );
            respond(['post' => $result], 201);
            break;

        case 'PUT':
            $body = json_body();
            $id = (int)($body['id'] ?? 0);
            $message = trim($body['message'] ?? '');
            if (!$id || $message === '') {
                respond(['error' => 'id and message are required'], 422);
            }
            respond(['post' => $api->updatePost($id, $message)]);
            break;

        case 'DELETE':
            $body = json_body();
            $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
            if (!$id) {
                respond(['error' => 'id is required'], 422);
            }
            $api->deletePost($id);
            respond(['deleted' => true]);
            break;

        default:
            respond(['error' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    respond_error($e, 500);
}
