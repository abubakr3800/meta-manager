<?php
require_once __DIR__ . '/_bootstrap.php';

if (!$identityId) {
    respond(['accounts' => []]);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Force a re-sync from Graph API
        respond(['accounts' => $api->syncAdAccounts($identityId)]);
    }
    $accounts = $api->listAdAccounts($identityId);
    if (empty($accounts)) {
        $accounts = $api->syncAdAccounts($identityId);
    }
    respond(['accounts' => $accounts]);
} catch (Throwable $e) {
    respond_error($e, 500);
}
