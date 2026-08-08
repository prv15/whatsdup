<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use WhatstheUp\Support\Env;
use WhatstheUp\Support\HttpException;

final class MetaGraphClient
{
    public function exchangeCode(string $code): array
    {
        return $this->request('GET', '/oauth/access_token', null, [
            'client_id' => Env::get('META_APP_ID', ''),
            'client_secret' => Env::get('META_APP_SECRET', ''),
            'code' => $code,
        ]);
    }

    public function getWaba(string $wabaId, string $token): array
    {
        return $this->request('GET', '/' . rawurlencode($wabaId), $token, ['fields' => 'id,name,currency,timezone_id,account_review_status,owner_business_info']);
    }

    public function getPhone(string $phoneId, string $token): array
    {
        return $this->request('GET', '/' . rawurlencode($phoneId), $token, ['fields' => 'id,display_phone_number,verified_name,quality_rating,name_status']);
    }

    public function subscribeWaba(string $wabaId, string $token): array
    {
        return $this->request('POST', '/' . rawurlencode($wabaId) . '/subscribed_apps', $token);
    }

    public function getTemplates(string $wabaId, string $token): array
    {
        return $this->request('GET', '/' . rawurlencode($wabaId) . '/message_templates', $token, ['fields' => 'id,name,status,language,category,components', 'limit' => '250']);
    }

    public function sendTemplate(string $phoneNumberId, string $token, string $to, string $templateName, string $language): array
    {
        return $this->request('POST', '/' . rawurlencode($phoneNumberId) . '/messages', $token, [], [
            'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
            'template' => ['name' => $templateName, 'language' => ['code' => $language]],
        ]);
    }

    private function request(string $method, string $path, ?string $token = null, array $query = [], ?array $payload = null): array
    {
        $version = Env::get('META_GRAPH_API_VERSION', '') ?? '';
        if (!preg_match('/^v\d+\.\d+$/', $version)) {
            throw new \RuntimeException('META_GRAPH_API_VERSION is invalid.');
        }
        $url = 'https://graph.facebook.com/' . $version . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $handle = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30];
        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
        }
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);
        if ($body === false || $curlError !== '') {
            throw new HttpException(502, 'Meta could not be reached. Try again shortly.', 'meta_unavailable');
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new HttpException(502, 'Meta returned an unreadable response.', 'meta_invalid_response');
        }
        if ($status < 200 || $status >= 300 || isset($decoded['error'])) {
            $message = (string) ($decoded['error']['message'] ?? 'Meta rejected the request.');
            $code = (string) ($decoded['error']['code'] ?? 'meta_request_failed');
            throw new HttpException(422, $message, 'meta_' . preg_replace('/[^a-zA-Z0-9_]/', '', $code));
        }
        return $decoded;
    }
}
