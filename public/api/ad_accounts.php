<?php
require_once __DIR__ . '/_bootstrap.php';

if (!$identityId) {
    respond(['accounts' => []]);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        respond(['accounts' => $api->syncAdAccounts($identityId)]);
    }
    $accounts = $api->listAdAccounts($identityId);
    if (!empty($accounts)) {
        respond(['accounts' => $accounts]);
    }
    try {
        respond(['accounts' => $api->syncAdAccounts($identityId)]);
    } catch (GraphApiException $e) {
        if ($e->getCode() === 200) {
            respond([
                'accounts' => [],
                'warning'  => 'Missing ads_read. In Facebook Login for Business configuration ' . META_LOGIN_CONFIG_ID . ' add ads_read and ads_management, save, then Reconnect Meta.',
            ]);
        }
        throw $e;
    }
} catch (Throwable $e) {
    respond_error($e, 500);
}
