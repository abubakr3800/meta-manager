<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/MetaAuth.php';

$method = $_SERVER['REQUEST_METHOD'];

if (!$identityId) {
    respond(['error' => 'No connected Meta identity'], 422);
}

try {
    $userToken = MetaAuth::getDecryptedUserToken($identityId);

    switch ($method) {
        case 'GET':
            $actId = $_GET['act_id'] ?? '';
            if (!$actId) {
                respond(['error' => 'act_id is required'], 422);
            }
            respond(['campaigns' => $api->listCampaigns($actId, $userToken)]);
            break;

        case 'POST':
            $body = json_body();
            $actId = $body['act_id'] ?? '';
            $name = trim($body['name'] ?? '');
            $objective = $body['objective'] ?? '';
            if (!$actId || $name === '' || !$objective) {
                respond(['error' => 'act_id, name and objective are required'], 422);
            }
            $result = $api->createCampaign($actId, $userToken, $name, $objective, $body['status'] ?? 'PAUSED');
            respond(['campaign' => $result], 201);
            break;

        case 'PUT':
            $body = json_body();
            $fbCampaignId = $body['fb_campaign_id'] ?? '';
            if (!$fbCampaignId) {
                respond(['error' => 'fb_campaign_id is required'], 422);
            }
            $result = $api->updateCampaign($fbCampaignId, $userToken, $body);
            respond(['campaign' => $result]);
            break;

        case 'DELETE':
            $body = json_body();
            $fbCampaignId = $body['fb_campaign_id'] ?? ($_GET['fb_campaign_id'] ?? '');
            if (!$fbCampaignId) {
                respond(['error' => 'fb_campaign_id is required'], 422);
            }
            $api->deleteCampaign($fbCampaignId, $userToken);
            respond(['deleted' => true]);
            break;

        default:
            respond(['error' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    respond_error($e, 500);
}
