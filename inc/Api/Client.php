<?php
namespace spotplayer\inc\Api;

defined('ABSPATH') || exit;

final class Client {
    private $base = 'https://panel.spotplayer.ir';

    private function request(string $url, array $data = []) {
        if (!class_exists('\Requests')) {
            // Fallback to WordPress HTTP if Requests not available (should be in WP >=4.6)
            $args = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    '$Level'       => '-1',
                    '$API'         => get_option('spotplayer')['api'] ?? '',
                    'X-WpSpot'     => SPOTPLAYER_VERSION,
                ],
                'timeout' => 20,
                'sslverify' => true,
                'body' => $data ? wp_json_encode($data) : null,
            ];
            $response = $data ? wp_remote_post($url, $args) : wp_remote_get($url, $args);
            if (is_wp_error($response)) throw new \Exception($response->get_error_message());
            $body = wp_remote_retrieve_body($response);
            $rep = json_decode($body, true);
        } else {
            $headers = [
                'Content-Type' => 'application/json',
                '$Level'       => '-1',
                '$API'         => get_option('spotplayer')['api'] ?? '',
                'X-WpSpot'     => SPOTPLAYER_VERSION,
            ];
            $method = $data ? 'POST' : 'GET';
            $body = $data ? wp_json_encode($data, JSON_UNESCAPED_UNICODE) : null;
            $resp = \Requests::request($url, $headers, $body, $method, ['timeout' => 20, 'verify' => true]);
            $rep = json_decode($resp->body ?? '', true);
        }
        if (isset($rep['ex'])) {
            $msg = is_array($rep['ex']) && isset($rep['ex']['msg']) ? $rep['ex']['msg'] : __('پاسخ نامعتبر از سرور', 'spotplayer');
            throw new \Exception($msg);
        }
        return $rep;
    }

    public function license_get(string $id): array {
        return $this->request($this->base . '/license/edit/' . rawurlencode($id) . '?d=1');
    }

    public function license_put(array $payload): array {
        $opt = get_option('spotplayer', []);
        if (!empty($opt['test'])) $payload['test'] = 1;
        return $this->request($this->base . '/license/edit/', $payload);
    }
}
