<?php
require_once __DIR__ . '/../includes/Session.php';
require_once __DIR__ . '/../config/config.php';

$adminId = Session::requireLogin();
$identityId = Session::currentIdentityId();
$grantedScopes = '';
if ($identityId) {
    $stmt = Database::pdo()->prepare('SELECT scopes FROM meta_identities WHERE id = :id');
    $stmt->execute(['id' => $identityId]);
    $grantedScopes = (string)$stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div id="fb-root"></div>

<header class="topbar">
  <div class="topbar-inner">
    <img src="https://shortcircuit.company/assets/img/logo.svg" alt="Short Circuit" class="topbar-logo">
    <h1 class="small-heading topbar-title">META MANAGER</h1>
    <div class="topbar-actions">
      <?php if ($identityId): ?>
        <span class="badge badge-connected">Meta Connected</span>
        <button type="button" class="btn btn-ghost" id="btnConnectMeta">Reconnect Meta</button>
      <?php else: ?>
        <button type="button" class="btn btn-primary" id="btnConnectMeta">Connect Meta Account</button>
      <?php endif; ?>
      <a href="oauth/logout.php" class="btn btn-ghost">Sign Out</a>
    </div>
  </div>
</header>

<main class="content">
  <?php if (!empty($_GET['meta_error'])): ?>
    <p class="alert alert-error"><?= htmlspecialchars((string)$_GET['meta_error']) ?></p>
  <?php endif; ?>
  <p class="alert alert-error" id="metaConnectError" hidden></p>
  <?php if ($identityId && $grantedScopes !== ''): ?>
    <p class="muted">Granted Facebook permissions: <code><?= htmlspecialchars($grantedScopes) ?></code>. If a tab shows Missing Permissions, add that permission to the Login for Business configuration, then click Reconnect Meta.</p>
  <?php endif; ?>
  <?php if (!$identityId): ?>
    <p class="muted">Facebook App ID in use: <code><?= htmlspecialchars(META_APP_ID) ?></code></p>
    <?php if (META_LOGIN_CONFIG_ID === ''): ?>
      <p class="alert alert-error">Missing Login for Business Config ID. In the app dashboard add use case <strong>Facebook Login for Business</strong>, create a <strong>User access token</strong> configuration with Pages / Instagram / Ads / Leads permissions, then put the Config ID in <code>.env</code> as <code>SC_META_LOGIN_CONFIG_ID</code>.</p>
    <?php endif; ?>
  <?php endif; ?>
  <?php if (!empty($_GET['connected'])): ?>
    <p class="alert alert-success">Meta account connected.</p>
  <?php endif; ?>

  <nav class="tabs" id="tabs">
    <button class="tab-btn is-active" data-tab="posts">Facebook Posts</button>
    <button class="tab-btn" data-tab="instagram">Instagram</button>
    <button class="tab-btn" data-tab="ads">Ads &amp; Campaigns</button>
    <button class="tab-btn" data-tab="leads">Leads</button>
  </nav>

  <section class="toolbar">
    <label class="page-select-label">Page
      <select id="pageSelect"><option value="">— Select a Page —</option></select>
    </label>
    <button type="button" class="btn btn-ghost" id="btnSyncPages">Sync Pages</button>
  </section>

  <!-- ============================== POSTS ============================== -->
  <section class="tab-panel is-active" id="panel-posts">
    <div class="panel-head">
      <h2 class="subheading">Facebook Page Posts</h2>
      <button class="btn btn-primary" id="btnNewPost">+ New Post</button>
    </div>
    <table class="data-table" id="postsTable">
      <thead><tr><th>Message</th><th>Link</th><th>Status</th><th>Created</th><th></th></tr></thead>
      <tbody></tbody>
    </table>
  </section>

  <!-- ============================ INSTAGRAM ============================ -->
  <section class="tab-panel" id="panel-instagram">
    <div class="panel-head">
      <h2 class="subheading">Instagram Media</h2>
      <button class="btn btn-primary" id="btnNewMedia">+ New Post</button>
    </div>
    <div class="media-grid" id="mediaGrid"></div>
  </section>

  <!-- ============================== ADS ================================ -->
  <section class="tab-panel" id="panel-ads">
    <div class="panel-head">
      <h2 class="subheading">Ad Campaigns</h2>
      <div class="panel-head-right">
        <select id="adAccountSelect"><option value="">— Select Ad Account —</option></select>
        <button class="btn btn-primary" id="btnNewCampaign">+ New Campaign</button>
      </div>
    </div>
    <table class="data-table" id="campaignsTable">
      <thead><tr><th>Name</th><th>Objective</th><th>Status</th><th>Daily Budget</th><th></th></tr></thead>
      <tbody></tbody>
    </table>
  </section>

  <!-- ============================= LEADS ================================ -->
  <section class="tab-panel" id="panel-leads">
    <div class="panel-head">
      <h2 class="subheading">Lead Forms &amp; Leads</h2>
      <div class="panel-head-right">
        <select id="leadFormSelect"><option value="">— Select Form —</option></select>
        <button class="btn btn-primary" id="btnSyncLeads">Sync Leads</button>
      </div>
    </div>
    <table class="data-table" id="leadsTable">
      <thead><tr><th>Lead ID</th><th>Field Data</th><th>Received</th><th></th></tr></thead>
      <tbody></tbody>
    </table>
  </section>

</main>

<!-- ============================ MODAL ============================ -->
<div class="modal-backdrop" id="modalBackdrop">
  <div class="modal" id="modal">
    <h3 class="subheading" id="modalTitle">Title</h3>
    <form id="modalForm"></form>
  </div>
</div>

<script>
window.SC_META = {
  appId: <?= json_encode(META_APP_ID) ?>,
  version: <?= json_encode(META_GRAPH_VERSION) ?>,
  configId: <?= json_encode(META_LOGIN_CONFIG_ID) ?>,
  connectUrl: <?= json_encode(app_path('api/meta_connect.php')) ?>,
  redirectTo: <?= json_encode(app_path('index.php') . '?connected=1') ?>,
  needsConnect: <?= $identityId ? 'false' : 'true' ?>
};
</script>
<script src="assets/js/meta-login.js"></script>
<script>
window.fbAsyncInit = function () {
  FB.init({
    appId: window.SC_META.appId,
    cookie: true,
    xfbml: true,
    version: window.SC_META.version
  });
  FB.AppEvents.logPageView();
};
(function (d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) { return; }
  js = d.createElement(s); js.id = id;
  js.src = 'https://connect.facebook.net/en_US/sdk.js';
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));
</script>
<script src="assets/js/app.js"></script>
</body>
</html>
