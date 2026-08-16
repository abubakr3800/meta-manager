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
        $cached = $pages;
        try {
            $pages = $api->syncPagesFromGraph($identityId);
        } catch (GraphApiException $e) {
            $pages = $cached;
            $warning = $e->getCode() === 200
                ? 'Missing pages_show_list. Add pages_show_list and pages_read_engagement to configuration ' . META_LOGIN_CONFIG_ID . ', then Reconnect Meta and tick your Pages in the Facebook dialog.'
                : $e->getMessage();
        }
        if (empty($pages)) {
            $pages = $cached;
        }
        if (empty($pages)) {
            $scopes = '';
            try {
                $scopes = MetaAuth::fetchGrantedScopes(MetaAuth::getDecryptedUserToken($identityId));
            } catch (Throwable $e) {
                $scopes = '';
            }
            $warning = $warning ?: (
                'Facebook returned no Pages. Granted permissions: [' . ($scopes ?: 'none') . ']. '
                . 'Edit configuration ' . META_LOGIN_CONFIG_ID . ': enable Pages as an asset and pages_show_list. '
                . 'Then click Reconnect Meta and in the popup select your Business + every Page.'
            );
        } elseif ($refresh && count($pages) === 1) {
            $warning = $warning ?: 'Showing 1 saved Page. To add more, click Reconnect Meta and tick every Page in the Facebook popup.';
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
