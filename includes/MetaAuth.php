<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Encryption.php';
require_once __DIR__ . '/GraphClient.php';

/**
 * Handles the OAuth2 Authorization Code flow against Facebook Login,
 * exchanges the short-lived token for a long-lived one, and persists
 * everything (encrypted) in MySQL.
 */
final class MetaAuth
{
    /** Step 1: build the URL the admin is redirected to. */
    public static function buildLoginUrl(int $adminUserId): string
    {
        $state = bin2hex(random_bytes(16));

        $stmt = Database::pdo()->prepare(
            'INSERT INTO oauth_states (state, admin_user_id) VALUES (:s, :u)'
        );
        $stmt->execute(['s' => $state, 'u' => $adminUserId]);

        $params = [
            'client_id'     => META_APP_ID,
            'redirect_uri'  => META_REDIRECT_URI,
            'state'         => $state,
            'scope'         => META_SCOPES,
            'response_type' => 'code',
        ];
        $configId = env('SC_META_LOGIN_CONFIG_ID');
        if ($configId) {
            $params['config_id'] = $configId;
        }

        return 'https://www.facebook.com/' . META_GRAPH_VERSION . '/dialog/oauth?' . http_build_query($params);
    }

    /** Step 2: validate the `state` param returned on callback (CSRF guard). */
    public static function consumeState(string $state, int $adminUserId): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id FROM oauth_states WHERE state = :s AND admin_user_id = :u
             AND created_at > (NOW() - INTERVAL 10 MINUTE)'
        );
        $stmt->execute(['s' => $state, 'u' => $adminUserId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        $pdo->prepare('DELETE FROM oauth_states WHERE id = :id')->execute(['id' => $row['id']]);
        return true;
    }

    /** Step 3: exchange the `code` for a user access token, then persist. */
    public static function handleCallback(string $code, int $adminUserId): int
    {
        $graph = new GraphClient();
        $tokenResp = $graph->get('/oauth/access_token', [
            'client_id'     => META_APP_ID,
            'client_secret' => META_APP_SECRET,
            'redirect_uri'  => META_REDIRECT_URI,
            'code'          => $code,
        ]);
        return self::persistFromShortLivedToken($adminUserId, $tokenResp['access_token']);
    }

    /** JS SDK returned a short-lived user token from FB.login(). */
    public static function handleJsAccessToken(int $adminUserId, string $accessToken): int
    {
        self::assertTokenForThisApp($accessToken);
        return self::persistFromShortLivedToken($adminUserId, $accessToken);
    }

    /** JS SDK Facebook Login for Business returned an OAuth code. */
    public static function handleJsCode(int $adminUserId, string $code): int
    {
        $graph = new GraphClient();
        $params = [
            'client_id'     => META_APP_ID,
            'client_secret' => META_APP_SECRET,
            'code'          => $code,
        ];
        $pageUri = sc_request_is_https()
            ? ('https://' . ($_SERVER['HTTP_HOST'] ?? '') . app_path('index.php'))
            : ('http://' . ($_SERVER['HTTP_HOST'] ?? '') . app_path('index.php'));
        try {
            $tokenResp = $graph->get('/oauth/access_token', $params);
        } catch (Throwable $e) {
            $params['redirect_uri'] = $pageUri;
            $tokenResp = $graph->get('/oauth/access_token', $params);
        }
        return self::persistFromShortLivedToken($adminUserId, $tokenResp['access_token']);
    }

    private static function assertTokenForThisApp(string $accessToken): void
    {
        $graph = new GraphClient();
        $debug = $graph->get('/debug_token', [
            'input_token'  => $accessToken,
            'access_token' => META_APP_ID . '|' . META_APP_SECRET,
        ]);
        $data = $debug['data'] ?? [];
        if (empty($data['is_valid']) || (string)($data['app_id'] ?? '') !== (string)META_APP_ID) {
            throw new RuntimeException('Facebook token is not valid for this app.');
        }
    }

    private static function persistFromShortLivedToken(int $adminUserId, string $shortLivedToken): int
    {
        $graph = new GraphClient();

        $longResp = $graph->get('/oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => META_APP_ID,
            'client_secret'     => META_APP_SECRET,
            'fb_exchange_token' => $shortLivedToken,
        ]);
        $longLivedToken = $longResp['access_token'];
        $expiresIn      = $longResp['expires_in'] ?? 5184000;

        $me = $graph->get('/me', ['access_token' => $longLivedToken, 'fields' => 'id,name']);

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO meta_identities (admin_user_id, fb_user_id, fb_name, access_token_enc, token_expires_at, scopes)
             VALUES (:admin, :fbid, :name, :tok, DATE_ADD(NOW(), INTERVAL :secs SECOND), :scopes)
             ON DUPLICATE KEY UPDATE
               fb_name = VALUES(fb_name),
               access_token_enc = VALUES(access_token_enc),
               token_expires_at = VALUES(token_expires_at),
               scopes = VALUES(scopes)'
        );
        $stmt->execute([
            'admin'  => $adminUserId,
            'fbid'   => $me['id'],
            'name'   => $me['name'] ?? null,
            'tok'    => Encryption::encrypt($longLivedToken),
            'secs'   => $expiresIn,
            'scopes' => META_SCOPES,
        ]);

        $identityId = (int)$pdo->lastInsertId();
        if ($identityId === 0) {
            $find = $pdo->prepare('SELECT id FROM meta_identities WHERE admin_user_id = :a AND fb_user_id = :f');
            $find->execute(['a' => $adminUserId, 'f' => $me['id']]);
            $identityId = (int)$find->fetchColumn();
        }

        self::syncPages($identityId, $longLivedToken);

        return $identityId;
    }

    /** Pull all Pages the user manages and store their (non-expiring) Page tokens. */
    public static function syncPages(int $identityId, string $userAccessToken): void
    {
        $graph = new GraphClient();
        $pages = $graph->get('/me/accounts', [
            'access_token' => $userAccessToken,
            'fields'       => 'id,name,category,access_token,instagram_business_account',
            'limit'        => 200,
        ]);

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO meta_pages (meta_identity_id, fb_page_id, page_name, page_access_token_enc, instagram_business_id, category)
             VALUES (:identity, :pid, :name, :tok, :ig, :cat)
             ON DUPLICATE KEY UPDATE
               page_name = VALUES(page_name),
               page_access_token_enc = VALUES(page_access_token_enc),
               instagram_business_id = VALUES(instagram_business_id),
               category = VALUES(category)'
        );

        foreach ($pages['data'] ?? [] as $page) {
            $stmt->execute([
                'identity' => $identityId,
                'pid'      => $page['id'],
                'name'     => $page['name'] ?? null,
                'tok'      => Encryption::encrypt($page['access_token']),
                'ig'       => $page['instagram_business_account']['id'] ?? null,
                'cat'      => $page['category'] ?? null,
            ]);
        }
    }

    public static function getDecryptedPageToken(int $metaPageId): string
    {
        $stmt = Database::pdo()->prepare('SELECT page_access_token_enc FROM meta_pages WHERE id = :id');
        $stmt->execute(['id' => $metaPageId]);
        $enc = $stmt->fetchColumn();
        if (!$enc) {
            throw new RuntimeException('Page not found');
        }
        return Encryption::decrypt($enc);
    }

    public static function getDecryptedUserToken(int $identityId): string
    {
        $stmt = Database::pdo()->prepare('SELECT access_token_enc FROM meta_identities WHERE id = :id');
        $stmt->execute(['id' => $identityId]);
        $enc = $stmt->fetchColumn();
        if (!$enc) {
            throw new RuntimeException('Identity not found');
        }
        return Encryption::decrypt($enc);
    }
}
