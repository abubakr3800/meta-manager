# Short Circuit — Meta (Facebook/Instagram) Manager

A full CRUD admin panel for the Meta Graph API (Facebook Pages, Instagram,
Marketing/Ads, Lead Ads) using **OAuth2**, **PHP 8+ / MySQL**, and vanilla
**JS**. Visual identity follows the SC brand guidelines at
[shortcircuit.company/SCbrand](https://shortcircuit.company/SCbrand)
(Anton headlines, Poppins body, IBM Plex Sans Arabic for Arabic, Primary
Red `#EB1B26`, golden-ratio type scale, 80% content width).

## What it manages


| Module              | Create                        | Read            | Update                                  | Delete                   |
| ------------------- | ----------------------------- | --------------- | --------------------------------------- | ------------------------ |
| Facebook Page Posts | ✅                             | ✅               | ✅                                       | ✅                        |
| Instagram Media     | ✅                             | ✅               | — (IG API has no caption-edit endpoint) | ✅                        |
| Ad Campaigns        | ✅                             | ✅               | ✅ (name/status/budget)                  | ✅ (soft: status=DELETED) |
| Lead Ads            | — (leads originate from Meta) | ✅ (sync + list) | —                                       | ✅ (local erasure only)   |




## Directory layout

```
config/        App + DB configuration
includes/      GraphClient, MetaAuth (OAuth2), MetaAPI (CRUD), Encryption, Session
public/        Web root — point your vhost here
  index.php        Main dashboard (tabs: Posts / Instagram / Ads / Leads)
  login.php        Admin login (local accounts, separate from Meta OAuth2)
  oauth/           login.php, callback.php, logout.php — the OAuth2 flow
  api/             JSON endpoints consumed by assets/js/app.js
  assets/          Brand-compliant CSS + JS
sql/schema.sql Database schema
```



## 1. Create the Meta App

1. Go to [https://developers.facebook.com/apps](https://developers.facebook.com/apps) → **Create App** → type **Business**.
2. Add products: **Facebook Login**, **Pages API**, **Instagram Graph API**,
  **Marketing API**.
3. Under **Facebook Login → Settings**, add a valid OAuth redirect URI:
  `https://yourdomain.com/public/oauth/callback.php`
4. Note your **App ID** and **App Secret**.
5. Request the permissions listed in `config/config.php` (`META_SCOPES`)
  via App Review before going live — in development mode they work for
   admins/testers of the app only.



## 2. Database

```bash
mysql -u root -p < sql/schema.sql
```

Create your first admin user (local login, separate from the Meta OAuth2
identity):

```bash
php -r "echo password_hash('YourStrongPassword!', PASSWORD_DEFAULT);"
```

```sql
INSERT INTO admin_users (name, email, password_hash, role)
VALUES ('Abubakr', 'you@shortcircuit.company', '<paste hash>', 'owner');
```



## 3. Configure

Set these as environment variables (preferred) or edit `config/config.php`
directly for local testing:

```
SC_DB_HOST=127.0.0.1
SC_DB_NAME=sc_meta_manager
SC_DB_USER=sc_meta_user
SC_DB_PASS=********
SC_META_APP_ID=xxxxxxxxxxxxxxx
SC_META_APP_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
SC_META_REDIRECT_URI=https://yourdomain.com/public/oauth/callback.php
SC_TOKEN_ENC_KEY=<64 hex chars — generate with: php -r "echo bin2hex(random_bytes(32));">
```

**Never commit real secrets.** `SC_TOKEN_ENC_KEY` encrypts every stored
access token (AES-256-GCM) — losing it means every stored token becomes
unreadable, so back it up somewhere safe (e.g. your password manager or
secrets vault), not in the repo.

## 4. Point your webserver at `public/`

Apache example (`public/` as DocumentRoot), PHP-FPM or `mod_php` enabled,
`curl` and `openssl` PHP extensions on:

```apache
<VirtualHost *:443>
  ServerName yourdomain.com
  DocumentRoot /var/www/meta-manager/public
  <Directory /var/www/meta-manager/public>
    AllowOverride All
    Require all granted
  </Directory>
</VirtualHost>
```



## 5. Use it

1. Visit `/public/login.php`, sign in with the admin account you created.
2. Click **Connect Meta Account** → complete the Facebook OAuth2 consent
  screen → you're redirected back and your Pages (and their Page Access
   Tokens) are pulled and stored encrypted.
3. Pick a Page from the dropdown, then use the tabs:
  - **Facebook Posts** — create/edit/delete/schedule posts.
  - **Instagram** — publish image posts to the Page's linked IG Business
  account (requires `instagram_business_account` to be set on the Page).
  - **Ads & Campaigns** — pick an Ad Account, create/pause/activate/delete
  campaigns.
  - **Leads** — pick a lead-gen form, **Sync Leads** to pull new
  submissions from Meta into MySQL, review, or erase local copies.



## Notes & limits (by Meta API design, not a bug here)

- **Instagram captions cannot be edited** after publishing — delete and
repost is the only path the Graph API exposes.
- **Instagram media deletion** is not permitted by Meta for most accounts;
the delete call will surface Meta's own error message if blocked.
- **Ad "deletion"** is actually `status = DELETED` — Meta's Marketing API
has no hard-delete for campaigns/ad sets/ads (this preserves reporting
history on their side).
- **Leads can't be deleted upstream** through the Graph API by third-party
apps — for GDPR/erasure requests, implement Meta's [Data Deletion
Request callback](https://developers.facebook.com/docs/development/create-an-app/app-dashboard/data-deletion-callback/)
in addition to this tool's local delete.
- Page Access Tokens obtained via `/me/accounts` do not expire as long as
the parent long-lived user token is valid (~60 days); re-run the OAuth2
flow periodically or add a refresh job that re-exchanges before expiry.



## Brand compliance

`assets/css/style.css` implements the SC identity system directly:
Anton for EN headlines (all caps), Poppins for EN body/subheadings,
IBM Plex Sans Arabic available via `[lang="ar"]`/`.rtl`, the `#EB1B26` /
`#A40E16` / `#000` / `#CCC` / `#FFF` palette, the gradient used sparingly
(buttons only), a golden-ratio (×1.618) type scale, and the logo's fixed
1:0.445 width-to-height ratio enforced in CSS wherever the logo appears.