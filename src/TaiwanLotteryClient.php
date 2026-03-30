<?php

declare(strict_types=1);

namespace OilApp;

use RuntimeException;

final class TaiwanLotteryClient
{
    private const API_BASE = 'https://api.taiwanlottery.com/TLCAPIWeB';

    public function __construct(
        private readonly array $config
    ) {
    }

    public function fetchSuperLotto638(array $query): array
    {
        $payload = $this->request('/Lottery/SuperLotto638Result', $query);

        return $payload['superLotto638Res'] ?? [];
    }

    public function fetchLotto649(array $query): array
    {
        $payload = $this->request('/Lottery/Lotto649Result', $query);

        return $payload['lotto649Res'] ?? [];
    }

    public function fetchDaily539(array $query): array
    {
        $payload = $this->request('/Lottery/Daily539Result', $query);

        return $payload['daily539Res'] ?? [];
    }

    private function request(string $path, array $query): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required to fetch Taiwan Lottery data.');
        }

        $url = self::API_BASE . $path . '?' . http_build_query($query);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => $this->config['scraper']['user_agent'],
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Failed to download Taiwan Lottery data: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new RuntimeException('Taiwan Lottery API returned HTTP ' . $statusCode . '.');
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !isset($decoded['content']) || !is_array($decoded['content'])) {
            throw new RuntimeException('Taiwan Lottery API returned an unexpected payload.');
        }

        return $decoded['content'];
    }
}
