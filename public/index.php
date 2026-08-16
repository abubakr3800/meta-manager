<?php
require_once __DIR__ . '/../includes/Session.php';
require_once __DIR__ . '/../config/config.php';

$adminId = Session::requireLogin();
$identityId = Session::currentIdentityId();
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

<header class="topbar">
  <div class="topbar-inner">
    <img src="https://shortcircuit.company/assets/img/logo.svg" alt="Short Circuit" class="topbar-logo">
    <h1 class="small-heading topbar-title">META MANAGER</h1>
    <div class="topbar-actions">
      <?php if ($identityId): ?>
        <span class="badge badge-connected">Meta Connected</span>
      <?php else: ?>
        <a href="oauth/login.php" class="btn btn-primary">Connect Meta Account</a>
      <?php endif; ?>
      <a href="oauth/logout.php" class="btn btn-ghost">Sign Out</a>
    </div>
  </div>
</header>

<main class="content">

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

<script src="assets/js/app.js"></script>
</body>
</html>
