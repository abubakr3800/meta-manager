<?php
require_once __DIR__ . '/../../includes/Session.php';
require_once __DIR__ . '/../../config/config.php';

Session::requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connect Meta — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
  <div class="auth-card">
    <h1 class="small-heading">CONNECT META</h1>
    <p>A Facebook popup will open. Allow it, then you will return to the dashboard.</p>
    <p class="alert alert-error" id="metaConnectError" hidden></p>
    <button type="button" class="btn btn-primary" id="btnConnectMeta">Continue with Facebook</button>
    <p><a href="../index.php">Back to dashboard</a></p>
  </div>
<script>
window.SC_META = {
  appId: <?= json_encode(META_APP_ID) ?>,
  version: <?= json_encode(META_GRAPH_VERSION) ?>,
  scopes: <?= json_encode(META_SCOPES) ?>,
  configId: <?= json_encode(META_LOGIN_CONFIG_ID) ?>,
  connectUrl: '../api/meta_connect.php',
  redirectTo: '../index.php?connected=1',
  autoStart: false
};
window.fbAsyncInit = function () {
  FB.init({
    appId: window.SC_META.appId,
    cookie: true,
    xfbml: false,
    version: window.SC_META.version
  });
};
</script>
<script async defer src="https://connect.facebook.net/en_US/sdk.js"></script>
<script src="../assets/js/meta-login.js"></script>
</body>
</html>
