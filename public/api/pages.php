<?php
require_once __DIR__ . '/_bootstrap.php';

if (!$identityId) {
    respond(['pages' => [], 'connected' => false]);
}

try {
    $pages = $api->listPages($identityId);
    respond(['pages' => $pages, 'connected' => true]);
} catch (Throwable $e) {
    respond_error($e, 500);
}
