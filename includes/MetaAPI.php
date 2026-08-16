<?php
require_once __DIR__ . '/GraphClient.php';
require_once __DIR__ . '/MetaAuth.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Domain-level CRUD operations against the Graph API, each one also
 * mirroring state into MySQL so the UI can list things without an
 * API round trip every time.
 */
final class MetaAPI
{
    private GraphClient $graph;

    public function __construct()
    {
        $this->graph = new GraphClient();
    }

    // ===================================================================
    // PAGES
    // ===================================================================

    public function listPages(int $identityId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, fb_page_id, page_name, instagram_business_id, category
             FROM meta_pages WHERE meta_identity_id = :i ORDER BY page_name'
        );
        $stmt->execute(['i' => $identityId]);
        return $stmt->fetchAll();
    }

    // ===================================================================
    // FACEBOOK PAGE POSTS — full CRUD
    // ===================================================================

    public function createPost(int $metaPageId, string $message, ?string $link = null, ?string $imageUrl = null, ?string $scheduledAt = null): array
    {
        $pageToken = MetaAuth::getDecryptedPageToken($metaPageId);
        $fbPageId  = $this->fbPageId($metaPageId);

        $params = ['message' => $message, 'access_token' => $pageToken];
        if ($link) {
            $params['link'] = $link;
        }
        if ($scheduledAt) {
            $params['published']              = 'false';
            $params['scheduled_publish_time'] = strtotime($scheduledAt);
        }

        $endpoint = $imageUrl ? "/{$fbPageId}/photos" : "/{$fbPageId}/feed";
        if ($imageUrl) {
            $params['url']     = $imageUrl;
            $params['caption'] = $message;
            unset($params['message']);
        }

        $resp = $this->graph->post($endpoint, $params);
        $fbPostId = $resp['post_id'] ?? $resp['id'] ?? null;

        $stmt = Database::pdo()->prepare(
            'INSERT INTO fb_posts (meta_page_id, fb_post_id, message, link, image_url, status, scheduled_publish_time)
             VALUES (:page, :fid, :msg, :link, :img, :status, :sched)'
        );
        $stmt->execute([
            'page'   => $metaPageId,
            'fid'    => $fbPostId,
            'msg'    => $message,
            'link'   => $link,
            'img'    => $imageUrl,
            'status' => $scheduledAt ? 'scheduled' : 'published',
            'sched'  => $scheduledAt,
        ]);

        return ['id' => (int)Database::pdo()->lastInsertId(), 'fb_post_id' => $fbPostId];
    }

    public function listPosts(int $metaPageId, int $limit = 25): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM fb_posts WHERE meta_page_id = :p AND status != "deleted" ORDER BY created_at DESC LIMIT :lim'
        );
        $stmt->bindValue('p', $metaPageId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function updatePost(int $localPostId, string $message): array
    {
        $post = $this->localPost($localPostId);
        $pageToken = MetaAuth::getDecryptedPageToken($post['meta_page_id']);

        if ($post['fb_post_id']) {
            $this->graph->post("/{$post['fb_post_id']}", [
                'message'      => $message,
                'access_token' => $pageToken,
            ]);
        }

        $stmt = Database::pdo()->prepare('UPDATE fb_posts SET message = :m WHERE id = :id');
        $stmt->execute(['m' => $message, 'id' => $localPostId]);
        return ['id' => $localPostId, 'message' => $message];
    }

    public function deletePost(int $localPostId): bool
    {
        $post = $this->localPost($localPostId);
        $pageToken = MetaAuth::getDecryptedPageToken($post['meta_page_id']);

        if ($post['fb_post_id']) {
            $this->graph->delete("/{$post['fb_post_id']}", ['access_token' => $pageToken]);
        }

        $stmt = Database::pdo()->prepare('UPDATE fb_posts SET status = "deleted" WHERE id = :id');
        $stmt->execute(['id' => $localPostId]);
        return true;
    }

    // ===================================================================
    // INSTAGRAM MEDIA — create (container -> publish), read, delete
    // (Instagram Graph API has no "edit caption" endpoint; delete + repost)
    // ===================================================================

    public function createInstagramMedia(int $metaPageId, string $imageUrl, string $caption): array
    {
        $pageToken = MetaAuth::getDecryptedPageToken($metaPageId);
        $igUserId  = $this->igBusinessId($metaPageId);

        // Step 1: create media container
        $container = $this->graph->post("/{$igUserId}/media", [
            'image_url'    => $imageUrl,
            'caption'      => $caption,
            'access_token' => $pageToken,
        ]);
        $containerId = $container['id'];

        // Step 2: publish container
        $publish = $this->graph->post("/{$igUserId}/media_publish", [
            'creation_id'  => $containerId,
            'access_token' => $pageToken,
        ]);
        $mediaId = $publish['id'];

        $stmt = Database::pdo()->prepare(
            'INSERT INTO ig_media (meta_page_id, ig_media_id, ig_container_id, caption, media_url, media_type, status)
             VALUES (:p, :mid, :cid, :cap, :url, "IMAGE", "published")'
        );
        $stmt->execute([
            'p'   => $metaPageId,
            'mid' => $mediaId,
            'cid' => $containerId,
            'cap' => $caption,
            'url' => $imageUrl,
        ]);

        return ['id' => (int)Database::pdo()->lastInsertId(), 'ig_media_id' => $mediaId];
    }

    public function listInstagramMedia(int $metaPageId, int $limit = 25): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM ig_media WHERE meta_page_id = :p AND status != "deleted" ORDER BY created_at DESC LIMIT :lim'
        );
        $stmt->bindValue('p', $metaPageId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function deleteInstagramMedia(int $localMediaId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM ig_media WHERE id = :id');
        $stmt->execute(['id' => $localMediaId]);
        $media = $stmt->fetch();
        if (!$media) {
            throw new RuntimeException('Media not found');
        }

        $pageToken = MetaAuth::getDecryptedPageToken($media['meta_page_id']);
        if ($media['ig_media_id']) {
            // Note: the Instagram Graph API generally does not allow deleting
            // published media; this call will surface a clear Graph error if so.
            $this->graph->delete("/{$media['ig_media_id']}", ['access_token' => $pageToken]);
        }

        Database::pdo()->prepare('UPDATE ig_media SET status = "deleted" WHERE id = :id')
            ->execute(['id' => $localMediaId]);
        return true;
    }

    // ===================================================================
    // AD ACCOUNTS
    // ===================================================================

    public function syncAdAccounts(int $identityId): array
    {
        $userToken = MetaAuth::getDecryptedUserToken($identityId);
        $accounts = $this->graph->get('/me/adaccounts', [
            'fields'       => 'account_id,name,currency',
            'access_token' => $userToken,
        ])['data'] ?? [];

        $stmt = Database::pdo()->prepare(
            'INSERT INTO ad_accounts (meta_identity_id, act_id, account_name, currency)
             VALUES (:i, :act, :name, :cur)
             ON DUPLICATE KEY UPDATE account_name = VALUES(account_name), currency = VALUES(currency)'
        );
        foreach ($accounts as $acc) {
            $stmt->execute([
                'i'    => $identityId,
                'act'  => 'act_' . $acc['account_id'],
                'name' => $acc['name'] ?? null,
                'cur'  => $acc['currency'] ?? null,
            ]);
        }
        return $this->listAdAccounts($identityId);
    }

    public function listAdAccounts(int $identityId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM ad_accounts WHERE meta_identity_id = :i ORDER BY account_name');
        $stmt->execute(['i' => $identityId]);
        return $stmt->fetchAll();
    }

    // ===================================================================
    // ADS: CAMPAIGNS — full CRUD via Marketing API
    // ===================================================================

    public function createCampaign(string $actId, string $userToken, string $name, string $objective, string $status = 'PAUSED'): array
    {
        $resp = $this->graph->post("/{$actId}/campaigns", [
            'name'                  => $name,
            'objective'             => $objective,
            'status'                => $status,
            'special_ad_categories' => json_encode([]),
            'access_token'          => $userToken,
        ]);

        $stmt = Database::pdo()->prepare(
            'SELECT id FROM ad_accounts WHERE act_id = :a LIMIT 1'
        );
        $stmt->execute(['a' => $actId]);
        $adAccountLocalId = $stmt->fetchColumn();

        $ins = Database::pdo()->prepare(
            'INSERT INTO ad_campaigns (ad_account_id, fb_campaign_id, name, objective, status)
             VALUES (:acc, :fid, :name, :obj, :status)'
        );
        $ins->execute([
            'acc'    => $adAccountLocalId,
            'fid'    => $resp['id'],
            'name'   => $name,
            'obj'    => $objective,
            'status' => $status,
        ]);

        return ['id' => (int)Database::pdo()->lastInsertId(), 'fb_campaign_id' => $resp['id']];
    }

    public function listCampaigns(string $actId, string $userToken): array
    {
        return $this->graph->get("/{$actId}/campaigns", [
            'fields'       => 'id,name,objective,status,daily_budget,created_time',
            'limit'        => 100,
            'access_token' => $userToken,
        ])['data'] ?? [];
    }

    public function updateCampaign(string $fbCampaignId, string $userToken, array $fields): array
    {
        $params = array_intersect_key($fields, array_flip(['name', 'status', 'daily_budget']));
        $params['access_token'] = $userToken;
        $this->graph->post("/{$fbCampaignId}", $params);

        Database::pdo()->prepare(
            'UPDATE ad_campaigns SET name = COALESCE(:name, name), status = COALESCE(:status, status) WHERE fb_campaign_id = :fid'
        )->execute([
            'name'   => $fields['name'] ?? null,
            'status' => $fields['status'] ?? null,
            'fid'    => $fbCampaignId,
        ]);

        return ['fb_campaign_id' => $fbCampaignId, 'updated' => $params];
    }

    public function deleteCampaign(string $fbCampaignId, string $userToken): bool
    {
        // Meta's Marketing API "deletes" campaigns by setting status = DELETED
        $this->graph->post("/{$fbCampaignId}", [
            'status'       => 'DELETED',
            'access_token' => $userToken,
        ]);
        Database::pdo()->prepare('UPDATE ad_campaigns SET status = "DELETED" WHERE fb_campaign_id = :fid')
            ->execute(['fid' => $fbCampaignId]);
        return true;
    }

    // ===================================================================
    // LEAD ADS — read forms + leads, local delete (GDPR/right-to-erasure)
    // ===================================================================

    public function syncLeadForms(int $metaPageId): array
    {
        $pageToken = MetaAuth::getDecryptedPageToken($metaPageId);
        $fbPageId  = $this->fbPageId($metaPageId);

        $forms = $this->graph->get("/{$fbPageId}/leadgen_forms", [
            'fields'       => 'id,name,status',
            'access_token' => $pageToken,
        ])['data'] ?? [];

        $stmt = Database::pdo()->prepare(
            'INSERT INTO lead_forms (meta_page_id, fb_form_id, form_name, status)
             VALUES (:p, :fid, :name, :status)
             ON DUPLICATE KEY UPDATE form_name = VALUES(form_name), status = VALUES(status)'
        );
        foreach ($forms as $form) {
            $stmt->execute([
                'p'      => $metaPageId,
                'fid'    => $form['id'],
                'name'   => $form['name'] ?? null,
                'status' => $form['status'] ?? null,
            ]);
        }
        return $forms;
    }

    public function syncLeads(int $localFormId): int
    {
        $stmt = Database::pdo()->prepare('SELECT lf.fb_form_id, lf.meta_page_id FROM lead_forms lf WHERE lf.id = :id');
        $stmt->execute(['id' => $localFormId]);
        $form = $stmt->fetch();
        if (!$form) {
            throw new RuntimeException('Lead form not found');
        }
        $pageToken = MetaAuth::getDecryptedPageToken($form['meta_page_id']);

        $leads = $this->graph->get("/{$form['fb_form_id']}/leads", [
            'fields'       => 'id,created_time,field_data',
            'access_token' => $pageToken,
        ])['data'] ?? [];

        $ins = Database::pdo()->prepare(
            'INSERT IGNORE INTO leads (lead_form_id, fb_lead_id, field_data_json, created_time)
             VALUES (:f, :lid, :fields, :ctime)'
        );
        foreach ($leads as $lead) {
            $ins->execute([
                'f'      => $localFormId,
                'lid'    => $lead['id'],
                'fields' => json_encode($lead['field_data'] ?? []),
                'ctime'  => date('Y-m-d H:i:s', strtotime($lead['created_time'])),
            ]);
        }
        return count($leads);
    }

    public function listLeads(int $localFormId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM leads WHERE lead_form_id = :f ORDER BY created_time DESC');
        $stmt->execute(['f' => $localFormId]);
        return $stmt->fetchAll();
    }

    public function deleteLeadLocally(int $localLeadId): bool
    {
        // Meta does not expose a delete-lead endpoint to apps; erasure of the
        // *local* copy is what this tool controls. Handle upstream erasure
        // requests via Meta's Data Deletion Request callback.
        Database::pdo()->prepare('DELETE FROM leads WHERE id = :id')->execute(['id' => $localLeadId]);
        return true;
    }

    // ===================================================================
    // Helpers
    // ===================================================================

    private function fbPageId(int $metaPageId): string
    {
        $stmt = Database::pdo()->prepare('SELECT fb_page_id FROM meta_pages WHERE id = :id');
        $stmt->execute(['id' => $metaPageId]);
        $v = $stmt->fetchColumn();
        if (!$v) {
            throw new RuntimeException('Page not found');
        }
        return $v;
    }

    private function igBusinessId(int $metaPageId): string
    {
        $stmt = Database::pdo()->prepare('SELECT instagram_business_id FROM meta_pages WHERE id = :id');
        $stmt->execute(['id' => $metaPageId]);
        $v = $stmt->fetchColumn();
        if (!$v) {
            throw new RuntimeException('This Page has no linked Instagram Business account');
        }
        return $v;
    }

    private function localPost(int $localPostId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM fb_posts WHERE id = :id');
        $stmt->execute(['id' => $localPostId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Post not found');
        }
        return $row;
    }
}
