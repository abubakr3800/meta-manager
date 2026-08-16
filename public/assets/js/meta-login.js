/* Meta JavaScript SDK login — Connect Meta Account */
(function () {
  const cfg = window.SC_META || {};
  const btn = document.getElementById('btnConnectMeta');
  const errEl = document.getElementById('metaConnectError');

  function showError(message) {
    if (!errEl) {
      window.alert(message);
      return;
    }
    errEl.hidden = false;
    errEl.textContent = message;
  }

  function loginOptions() {
    if (cfg.configId) {
      return {
        config_id: cfg.configId,
        response_type: 'code',
        override_default_response_type: true,
      };
    }
    return {
      scope: cfg.scopes || '',
      return_scopes: true,
      auth_type: 'rerequest',
    };
  }

  async function sendToServer(payload) {
    const connectUrl = cfg.connectUrl || 'api/meta_connect.php';
    const res = await fetch(connectUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(data.error || 'Failed to save Meta connection');
    }
  }

  async function onLoginResponse(response) {
    if (!response || response.status !== 'connected' || !response.authResponse) {
      const msg = response && response.status === 'unknown'
        ? 'Facebook login was cancelled or blocked. Allow pop-ups and try again.'
        : 'Facebook login did not complete.';
      showError(msg);
      return;
    }
    const auth = response.authResponse;
    try {
      if (auth.code) {
        await sendToServer({ code: auth.code });
      } else if (auth.accessToken) {
        await sendToServer({ accessToken: auth.accessToken });
      } else {
        throw new Error('Facebook did not return a token or code.');
      }
      window.location.href = cfg.redirectTo || 'index.php?connected=1';
    } catch (e) {
      showError(e.message || String(e));
    }
  }

  function connect() {
    if (typeof FB === 'undefined') {
      showError('Facebook SDK did not load. Check that this domain is in Allowed Domains for the JavaScript SDK.');
      return;
    }
    FB.login(onLoginResponse, loginOptions());
  }

  window.scMetaConnect = connect;

  if (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      connect();
    });
  }

  if (cfg.autoStart) {
    const start = function () {
      if (typeof FB !== 'undefined') {
        connect();
      } else {
        setTimeout(start, 200);
      }
    };
    start();
  }
})();
