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
            respond(['media' => $api->listInstagramMedia($pageId)]);
            break;

        case 'POST':
            $body = json_body();
            $pageId = (int)($body['page_id'] ?? 0);
            $imageUrl = trim($body['image_url'] ?? '');
            $caption  = trim($body['caption'] ?? '');
            if (!$pageId || $imageUrl === '') {
                respond(['error' => 'page_id and image_url are required'], 422);
            }
            $result = $api->createInstagramMedia($pageId, $imageUrl, $caption);
            respond(['media' => $result], 201);
            break;

        case 'DELETE':
            $body = json_body();
            $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
            if (!$id) {
                respond(['error' => 'id is required'], 422);
            }
            $api->deleteInstagramMedia($id);
            respond(['deleted' => true]);
            break;

        default:
            respond(['error' => 'Method not allowed (Instagram captions cannot be edited via API — delete and repost)'], 405);
    }
} catch (Throwable $e) {
    respond_error($e, 500);
}
