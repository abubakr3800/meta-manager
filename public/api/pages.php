<?php
require_once __DIR__ . '/_bootstrap.php';

if (!$identityId) {
    respond(['pages' => [], 'connected' => false]);
}

try {
    $refresh = ($_SERVER['REQUEST_METHOD'] === 'POST') || isset($_GET['refresh']);
    $pages = $api->listPages($identityId);
    $warning = null;

    if ($refresh || empty($pages)) {
        try {
            $pages = $api->syncPagesFromGraph($identityId);
            if (empty($pages)) {
                $warning = 'Facebook returned no Pages. In the Facebook login popup, select every Page (and the Business portfolio) you want to manage. Also add pages_show_list to Login for Business configuration ' . META_LOGIN_CONFIG_ID . ', then Reconnect Meta.';
            }
        } catch (GraphApiException $e) {
            $warning = $e->getCode() === 200
                ? 'Missing pages_show_list. Add pages_show_list and pages_read_engagement to configuration ' . META_LOGIN_CONFIG_ID . ', then Reconnect Meta and tick your Pages in the Facebook dialog.'
                : $e->getMessage();
        }
    }

    respond([
        'pages'     => $pages,
        'connected' => true,
        'warning'   => $warning,
    ]);
} catch (Throwable $e) {
    respond_error($e, 500);
}
