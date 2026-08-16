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
    var payload = authResponse.code
      ? { code: authResponse.code }
      : (authResponse.accessToken ? { accessToken: authResponse.accessToken } : null);
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
    if (response.status === 'connected') {
      if (cfg.needsConnect || fromLogin) {
        persistAuth(response.authResponse);
      }
      return;
    }
    if (!fromLogin) {
      return;
    }
    if (response.status === 'not_authorized') {
      showError('Logged into Facebook, but this app is not authorized yet. Click Continue with Facebook.');
      return;
    }
    showError('Not logged into Facebook. Click Continue with Facebook.');
  };

  window.checkLoginState = function () {
    FB.getLoginStatus(function (response) {
      statusChangeCallback(response, true);
    });
  };

  window.scMetaConnect = function () {
    if (typeof FB === 'undefined') {
      showError('Facebook SDK did not load. Enable Login with the JavaScript SDK and add https://shortcircuit.company to Allowed Domains.');
      return;
    }
    var options = cfg.configId
      ? { config_id: cfg.configId }
      : { scope: 'public_profile,email' };
    FB.login(function (response) {
      statusChangeCallback(response, true);
    }, options);
  };
})();
