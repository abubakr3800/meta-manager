/* Official Facebook Login for websites: getLoginStatus + login button callback.
   FB.login callback must be a normal function (not async). */
(function () {
  var cfg = window.SC_META || {};

  function showError(message) {
    var errEl = document.getElementById('metaConnectError');
    if (!errEl) {
      window.alert(message);
      return;
    }
    errEl.hidden = false;
    errEl.textContent = message;
  }

  function sendToServer(payload) {
    var connectUrl = cfg.connectUrl || 'api/meta_connect.php';
    return fetch(connectUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) {
          throw new Error(data.error || 'Failed to save Meta connection');
        }
      });
    });
  }

  function persistAuth(authResponse) {
    if (!authResponse) {
      showError('Facebook did not return a token.');
      return;
    }
    // User-token Login for Business returns accessToken.
    // System-user configs return code (authorization-code grant).
    var payload = authResponse.accessToken
      ? { accessToken: authResponse.accessToken }
      : (authResponse.code ? { code: authResponse.code } : null);
    if (!payload) {
      showError('Facebook did not return a token or code.');
      return;
    }
    sendToServer(payload).then(function () {
      window.location.href = cfg.redirectTo || 'index.php?connected=1';
    }).catch(function (e) {
      showError(e.message || String(e));
    });
  }

  window.statusChangeCallback = function (response, fromLogin) {
    if (!response) {
      return;
    }
    if (response.status === 'connected' && fromLogin) {
      persistAuth(response.authResponse);
      return;
    }
    if (!fromLogin) {
      return;
    }
    if (response.status === 'not_authorized') {
      showError('Logged into Facebook, but this app is not authorized yet. Click Connect Meta Account.');
      return;
    }
    showError('Facebook login was cancelled. Click Connect Meta Account and select your Pages.');
  };

  window.checkLoginState = function () {
    window.scMetaConnect();
  };

  window.scMetaConnect = function () {
    if (!cfg.configId) {
      showError('Set SC_META_LOGIN_CONFIG_ID in .env from Facebook Login for Business → Configurations.');
      return;
    }
    if (typeof FB === 'undefined') {
      showError('Facebook SDK did not load. Enable Login with the JavaScript SDK and add https://shortcircuit.company to Allowed Domains.');
      return;
    }
    // User access token configuration: config_id only.
    // Do not set response_type=code — that is for System User tokens and
    // makes /oauth/access_token return 400 for this config.
    FB.login(function (response) {
      statusChangeCallback(response, true);
    }, {
      config_id: cfg.configId,
      auth_type: 'rerequest'
    });
  };

  var btn = document.getElementById('btnConnectMeta');
  if (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      window.scMetaConnect();
    });
  }
})();
