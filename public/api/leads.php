<?php
require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    // List lead forms for a page, syncing from Graph first
    if ($method === 'GET' && isset($_GET['page_id']) && !isset($_GET['form_id'])) {
        $pageId = (int)$_GET['page_id'];
        $forms = $api->syncLeadForms($pageId);
        respond(['forms' => $forms]);
    }

    // List leads for a given local form id
    if ($method === 'GET' && isset($_GET['form_id'])) {
        $formId = (int)$_GET['form_id'];
        respond(['leads' => $api->listLeads($formId)]);
    }

    // Pull fresh leads from Graph API into MySQL
    if ($method === 'POST') {
        $body = json_body();
        $formId = (int)($body['form_id'] ?? 0);
        if (!$formId) {
            respond(['error' => 'form_id is required'], 422);
        }
        $count = $api->syncLeads($formId);
        respond(['synced' => $count]);
    }

    // Delete local copy of a lead (erasure request)
    if ($method === 'DELETE') {
        $body = json_body();
        $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
        if (!$id) {
            respond(['error' => 'id is required'], 422);
        }
        $api->deleteLeadLocally($id);
        respond(['deleted' => true]);
    }

    respond(['error' => 'Invalid request'], 400);
} catch (Throwable $e) {
    respond_error($e, 500);
}
