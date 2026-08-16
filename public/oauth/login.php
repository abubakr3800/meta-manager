<?php
require_once __DIR__ . '/../../includes/Session.php';
require_once __DIR__ . '/../../includes/MetaAuth.php';

$adminId = Session::requireLogin();
header('Location: ' . MetaAuth::buildLoginUrl($adminId));
exit;
