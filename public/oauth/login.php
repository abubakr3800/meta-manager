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
<div id="fb-root"></div>
  <div class="auth-card">
    <h1 class="small-heading">CONNECT META</h1>
    <p>Continue with Facebook, then you will return to the dashboard.</p>
    <p class="alert alert-error" id="metaConnectError" hidden></p>
    <fb:login-button
      size="large"
      onlogin="checkLoginState();"
      <?php if (META_LOGIN_CONFIG_ID !== ''): ?>
      config_id="<?= htmlspecialchars(META_LOGIN_CONFIG_ID) ?>"
      <?php else: ?>
      scope="public_profile,email"
      <?php endif; ?>
    ></fb:login-button>
    <p><a href="../index.php">Back to dashboard</a></p>
  </div>
<script>
window.SC_META = {
  appId: <?= json_encode(META_APP_ID) ?>,
  version: <?= json_encode(META_GRAPH_VERSION) ?>,
  configId: <?= json_encode(META_LOGIN_CONFIG_ID) ?>,
  connectUrl: '../api/meta_connect.php',
  redirectTo: '../index.php?connected=1',
  needsConnect: true
};
</script>
<script src="../assets/js/meta-login.js"></script>
<script>
window.fbAsyncInit = function () {
  FB.init({
    appId: window.SC_META.appId,
    cookie: true,
    xfbml: true,
    version: window.SC_META.version
  });
  FB.AppEvents.logPageView();
  FB.getLoginStatus(function (response) {
    statusChangeCallback(response, false);
  });
};
(function (d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) { return; }
  js = d.createElement(s); js.id = id;
  js.src = 'https://connect.facebook.net/en_US/sdk.js';
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));
</script>
</body>
</html>
