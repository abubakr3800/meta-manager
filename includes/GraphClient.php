<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Thin cURL wrapper around the Meta Graph API.
 * One place to handle base URL, error normalization, and file uploads.
 */
final class GraphClient
{
    private string $base;

    public function __construct()
    {
        $this->base = 'https://graph.facebook.com/' . META_GRAPH_VERSION;
    }

    public function get(string $path, array $params = []): array
    {
        return $this->request('GET', $path, $params);
    }

    public function post(string $path, array $params = []): array
    {
        return $this->request('POST', $path, $params);
    }

    public function delete(string $path, array $params = []): array
    {
        return $this->request('DELETE', $path, $params);
    }

    private function request(string $method, string $path, array $params): array
    {
        // Allow absolute URLs (e.g. /oauth/access_token uses graph.facebook.com root, not versioned)
        $url = str_starts_with($path, 'http')
            ? $path
            : $this->base . $path;

        $ch = curl_init();

        if ($method === 'GET') {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $err);
        }
        curl_close($ch);

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON from Graph API: ' . substr($raw, 0, 300));
        }

        if (isset($data['error'])) {
            $msg  = $data['error']['message'] ?? 'Unknown Graph API error';
            $code = $data['error']['code'] ?? 0;
            $sub  = $data['error']['error_subcode'] ?? null;
            throw new GraphApiException($msg, (int)$code, $sub);
        }

        return $data;
    }
}

final class GraphApiException extends RuntimeException
{
    public ?int $subcode;

    public function __construct(string $message, int $code, ?int $subcode = null)
    {
        parent::__construct($message, $code);
        $this->subcode = $subcode;
    }
}
