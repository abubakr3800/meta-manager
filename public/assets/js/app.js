/* =====================================================================
   Short Circuit Company — Meta Manager
   Vanilla JS front-end: tabs, page selector, and CRUD for
   Facebook Posts, Instagram Media, Ad Campaigns, Lead Ads.
   ===================================================================== */

const state = {
  pages: [],
  currentPageId: null,
  adAccounts: [],
  currentActId: null,
  leadForms: [],
  currentFormId: null,
};

// ----------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------
async function api(path, { method = 'GET', body } = {}) {
  const res = await fetch(path, {
    method,
    headers: body ? { 'Content-Type': 'application/json' } : {},
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error(data.error || `Request failed (${res.status})`);
  }
  return data;
}

function el(html) {
  const t = document.createElement('template');
  t.innerHTML = html.trim();
  return t.content.firstElementChild;
}

function statusPill(status) {
  return `<span class="status-pill status-${(status || '').toLowerCase()}">${status || ''}</span>`;
}

function escapeHtml(str) {
  const d = document.createElement('div');
  d.textContent = str ?? '';
  return d.innerHTML;
}

function toast(message, isError = false) {
  const bar = el(`<div class="alert ${isError ? 'alert-error' : 'alert-success'}" style="position:fixed;top:16px;right:16px;z-index:99;min-width:240px;">${escapeHtml(message)}</div>`);
  document.body.appendChild(bar);
  setTimeout(() => bar.remove(), 3500);
}

// ----------------------------------------------------------------------
// Tabs
// ----------------------------------------------------------------------
document.getElementById('tabs').addEventListener('click', (e) => {
  const btn = e.target.closest('.tab-btn');
  if (!btn) return;
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('is-active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('is-active'));
  btn.classList.add('is-active');
  document.getElementById(`panel-${btn.dataset.tab}`).classList.add('is-active');
  if (btn.dataset.tab === 'ads' && !state.adAccounts.length) {
    loadAdAccounts().catch((err) => toast(err.message, true));
  }
});

// ----------------------------------------------------------------------
// Modal (generic, reused by every CRUD form)
// ----------------------------------------------------------------------
const modalBackdrop = document.getElementById('modalBackdrop');
const modalTitle = document.getElementById('modalTitle');
const modalForm = document.getElementById('modalForm');

function openModal(title, fieldsHtml, onSubmit) {
  modalTitle.textContent = title;
  modalForm.innerHTML = fieldsHtml + `
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="modalCancel">Cancel</button>
      <button type="submit" class="btn btn-primary">Save</button>
    </div>`;
  modalBackdrop.classList.add('is-open');
  document.getElementById('modalCancel').onclick = closeModal;
  modalForm.onsubmit = async (e) => {
    e.preventDefault();
    const formData = Object.fromEntries(new FormData(modalForm).entries());
    try {
      await onSubmit(formData);
      closeModal();
    } catch (err) {
      toast(err.message, true);
    }
  };
}

function closeModal() {
  modalBackdrop.classList.remove('is-open');
  modalForm.onsubmit = null;
}

modalBackdrop.addEventListener('click', (e) => {
  if (e.target === modalBackdrop) closeModal();
});

// ----------------------------------------------------------------------
// Page selector (shared across Posts / Instagram / Leads tabs)
// ----------------------------------------------------------------------
const pageSelect = document.getElementById('pageSelect');

async function loadPages(refresh) {
  const data = await api('api/pages.php' + (refresh ? '?refresh=1' : ''));
  state.pages = data.pages || [];
  pageSelect.innerHTML = '<option value="">— Select a Page —</option>' +
    state.pages.map(p => `<option value="${p.id}">${escapeHtml(p.page_name || p.fb_page_id)}</option>`).join('');
  if (data.warning) {
    toast(data.warning, true);
  } else if (!data.connected) {
    toast('Connect your Meta account to load Pages.', true);
  } else if (!state.pages.length) {
    toast('No Pages yet. Reconnect Meta and select your Pages in the Facebook popup.', true);
  }
}

document.getElementById('btnSyncPages').addEventListener('click', async () => {
  try {
    await loadPages(true);
    toast('Pages refreshed.');
  } catch (err) {
    toast(err.message, true);
  }
});

pageSelect.addEventListener('change', () => {
  state.currentPageId = pageSelect.value || null;
  loadPosts();
  loadInstagramMedia();
  loadLeadForms();
});

// ----------------------------------------------------------------------
// FACEBOOK POSTS — CRUD
// ----------------------------------------------------------------------
const postsTbody = document.querySelector('#postsTable tbody');

async function loadPosts() {
  postsTbody.innerHTML = '';
  if (!state.currentPageId) return;
  const { posts } = await api(`api/posts.php?page_id=${state.currentPageId}`);
  postsTbody.innerHTML = posts.map(rowForPost).join('') || '<tr><td colspan="5">No posts yet.</td></tr>';
}

function rowForPost(p) {
  return `
    <tr data-id="${p.id}">
      <td>${escapeHtml(p.message || '')}</td>
      <td>${p.link ? `<a href="${escapeHtml(p.link)}" target="_blank">link</a>` : '—'}</td>
      <td>${statusPill(p.status)}</td>
      <td>${escapeHtml(p.created_at || '')}</td>
      <td>
        <button class="btn btn-sm btn-ghost" data-action="edit-post">Edit</button>
        <button class="btn btn-sm btn-danger" data-action="delete-post">Delete</button>
      </td>
    </tr>`;
}

document.getElementById('btnNewPost').addEventListener('click', () => {
  if (!state.currentPageId) return toast('Select a Page first.', true);
  openModal('New Facebook Post', `
    <div class="modal-form-row"><label>Message</label><textarea name="message" required></textarea></div>
    <div class="modal-form-row"><label>Link (optional)</label><input type="url" name="link"></div>
    <div class="modal-form-row"><label>Image URL (optional)</label><input type="url" name="image_url"></div>
    <div class="modal-form-row"><label>Schedule for (optional)</label><input type="datetime-local" name="scheduled_at"></div>
  `, async (data) => {
    await api('api/posts.php', { method: 'POST', body: { page_id: state.currentPageId, ...data } });
    toast('Post created.');
    loadPosts();
  });
});

postsTbody.addEventListener('click', async (e) => {
  const tr = e.target.closest('tr');
  if (!tr) return;
  const id = tr.dataset.id;

  if (e.target.dataset.action === 'delete-post') {
    if (!confirm('Delete this post? This cannot be undone.')) return;
    await api('api/posts.php', { method: 'DELETE', body: { id } });
    toast('Post deleted.');
    loadPosts();
  }

  if (e.target.dataset.action === 'edit-post') {
    const currentMessage = tr.children[0].textContent;
    openModal('Edit Post', `
      <div class="modal-form-row"><label>Message</label><textarea name="message" required>${escapeHtml(currentMessage)}</textarea></div>
    `, async (data) => {
      await api('api/posts.php', { method: 'PUT', body: { id, ...data } });
      toast('Post updated.');
      loadPosts();
    });
  }
});

// ----------------------------------------------------------------------
// INSTAGRAM MEDIA — Create / Read / Delete
// ----------------------------------------------------------------------
const mediaGrid = document.getElementById('mediaGrid');

async function loadInstagramMedia() {
  mediaGrid.innerHTML = '';
  if (!state.currentPageId) return;
  const { media } = await api(`api/instagram.php?page_id=${state.currentPageId}`);
  mediaGrid.innerHTML = media.map(cardForMedia).join('') || '<p>No Instagram media yet.</p>';
}

function cardForMedia(m) {
  return `
    <div class="media-card" data-id="${m.id}">
      <img src="${escapeHtml(m.media_url)}" alt="">
      <div class="media-card-body">
        <p class="media-card-caption">${escapeHtml(m.caption || '')}</p>
        ${statusPill(m.status)}
        <div style="margin-top:8px;">
          <button class="btn btn-sm btn-danger" data-action="delete-media">Delete</button>
        </div>
      </div>
    </div>`;
}

document.getElementById('btnNewMedia').addEventListener('click', () => {
  if (!state.currentPageId) return toast('Select a Page with a linked Instagram account first.', true);
  openModal('New Instagram Post', `
    <div class="modal-form-row"><label>Image URL</label><input type="url" name="image_url" required></div>
    <div class="modal-form-row"><label>Caption</label><textarea name="caption"></textarea></div>
  `, async (data) => {
    await api('api/instagram.php', { method: 'POST', body: { page_id: state.currentPageId, ...data } });
    toast('Instagram post published.');
    loadInstagramMedia();
  });
});

mediaGrid.addEventListener('click', async (e) => {
  if (e.target.dataset.action !== 'delete-media') return;
  const card = e.target.closest('.media-card');
  if (!confirm('Delete this Instagram media?')) return;
  try {
    await api('api/instagram.php', { method: 'DELETE', body: { id: card.dataset.id } });
    toast('Media deleted.');
    loadInstagramMedia();
  } catch (err) {
    toast(err.message, true);
  }
});

// ----------------------------------------------------------------------
// AD CAMPAIGNS — CRUD
// ----------------------------------------------------------------------
const adAccountSelect = document.getElementById('adAccountSelect');
const campaignsTbody = document.querySelector('#campaignsTable tbody');

async function loadAdAccounts() {
  const data = await api('api/ad_accounts.php');
  if (data.warning) {
    toast(data.warning, true);
  }
  state.adAccounts = data.accounts || [];
  adAccountSelect.innerHTML = '<option value="">— Select Ad Account —</option>' +
    state.adAccounts.map(a => `<option value="${a.act_id}">${escapeHtml(a.account_name || a.act_id)}</option>`).join('');
}

adAccountSelect.addEventListener('change', () => {
  state.currentActId = adAccountSelect.value || null;
  loadCampaigns();
});

async function loadCampaigns() {
  campaignsTbody.innerHTML = '';
  if (!state.currentActId) return;
  const { campaigns } = await api(`api/ads.php?act_id=${encodeURIComponent(state.currentActId)}`);
  campaignsTbody.innerHTML = campaigns.map(rowForCampaign).join('') || '<tr><td colspan="5">No campaigns yet.</td></tr>';
}

function rowForCampaign(c) {
  const budget = c.daily_budget ? `${(c.daily_budget / 100).toFixed(2)}` : '—';
  return `
    <tr data-fbid="${c.id}">
      <td>${escapeHtml(c.name)}</td>
      <td>${escapeHtml(c.objective || '')}</td>
      <td>${statusPill(c.status)}</td>
      <td>${budget}</td>
      <td>
        <button class="btn btn-sm btn-ghost" data-action="toggle-campaign" data-status="${c.status}">
          ${c.status === 'ACTIVE' ? 'Pause' : 'Activate'}
        </button>
        <button class="btn btn-sm btn-danger" data-action="delete-campaign">Delete</button>
      </td>
    </tr>`;
}

document.getElementById('btnNewCampaign').addEventListener('click', () => {
  if (!state.currentActId) return toast('Select an Ad Account first.', true);
  openModal('New Campaign', `
    <div class="modal-form-row"><label>Name</label><input type="text" name="name" required></div>
    <div class="modal-form-row"><label>Objective</label>
      <select name="objective" required>
        <option value="OUTCOME_AWARENESS">Awareness</option>
        <option value="OUTCOME_TRAFFIC">Traffic</option>
        <option value="OUTCOME_ENGAGEMENT">Engagement</option>
        <option value="OUTCOME_LEADS">Leads</option>
        <option value="OUTCOME_SALES">Sales</option>
      </select>
    </div>
  `, async (data) => {
    await api('api/ads.php', { method: 'POST', body: { act_id: state.currentActId, status: 'PAUSED', ...data } });
    toast('Campaign created.');
    loadCampaigns();
  });
});

campaignsTbody.addEventListener('click', async (e) => {
  const tr = e.target.closest('tr');
  if (!tr) return;
  const fbCampaignId = tr.dataset.fbid;

  if (e.target.dataset.action === 'delete-campaign') {
    if (!confirm('Delete this campaign?')) return;
    await api('api/ads.php', { method: 'DELETE', body: { fb_campaign_id: fbCampaignId } });
    toast('Campaign deleted.');
    loadCampaigns();
  }

  if (e.target.dataset.action === 'toggle-campaign') {
    const newStatus = e.target.dataset.status === 'ACTIVE' ? 'PAUSED' : 'ACTIVE';
    await api('api/ads.php', { method: 'PUT', body: { fb_campaign_id: fbCampaignId, status: newStatus } });
    toast(`Campaign ${newStatus === 'ACTIVE' ? 'activated' : 'paused'}.`);
    loadCampaigns();
  }
});

// ----------------------------------------------------------------------
// LEAD ADS — Forms + Leads
// ----------------------------------------------------------------------
const leadFormSelect = document.getElementById('leadFormSelect');
const leadsTbody = document.querySelector('#leadsTable tbody');

async function loadLeadForms() {
  leadFormSelect.innerHTML = '<option value="">— Select Form —</option>';
  if (!state.currentPageId) return;
  const { forms } = await api(`api/leads.php?page_id=${state.currentPageId}`);
  state.leadForms = forms || [];
  leadFormSelect.innerHTML += state.leadForms
    .map(f => `<option value="${f.id}">${escapeHtml(f.name || f.id)}</option>`).join('');
}

leadFormSelect.addEventListener('change', () => {
  state.currentFormId = leadFormSelect.value || null;
  loadLeads();
});

async function loadLeads() {
  leadsTbody.innerHTML = '';
  if (!state.currentFormId) return;
  const { leads } = await api(`api/leads.php?form_id=${state.currentFormId}`);
  leadsTbody.innerHTML = leads.map(rowForLead).join('') || '<tr><td colspan="4">No leads synced yet.</td></tr>';
}

function rowForLead(l) {
  let fields = '';
  try {
    fields = JSON.parse(l.field_data_json).map(f => `${f.name}: ${f.values?.[0] ?? ''}`).join('<br>');
  } catch (_) { fields = ''; }
  return `
    <tr data-id="${l.id}">
      <td>${escapeHtml(l.fb_lead_id)}</td>
      <td>${fields}</td>
      <td>${escapeHtml(l.created_time || '')}</td>
      <td><button class="btn btn-sm btn-danger" data-action="delete-lead">Delete</button></td>
    </tr>`;
}

document.getElementById('btnSyncLeads').addEventListener('click', async () => {
  if (!state.currentFormId) return toast('Select a lead form first.', true);
  try {
    const { synced } = await api('api/leads.php', { method: 'POST', body: { form_id: state.currentFormId } });
    toast(`Synced ${synced} lead(s).`);
    loadLeads();
  } catch (err) {
    toast(err.message, true);
  }
});

leadsTbody.addEventListener('click', async (e) => {
  if (e.target.dataset.action !== 'delete-lead') return;
  const tr = e.target.closest('tr');
  if (!confirm('Delete local copy of this lead?')) return;
  await api('api/leads.php', { method: 'DELETE', body: { id: tr.dataset.id } });
  toast('Lead deleted locally.');
  loadLeads();
});

// ----------------------------------------------------------------------
// Init
// ----------------------------------------------------------------------
(async function init() {
  try {
    await loadPages(true);
  } catch (err) {
    toast(err.message, true);
  }
})();
