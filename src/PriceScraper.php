<?php

declare(strict_types=1);

namespace OilApp;

use DateTimeImmutable;
use RuntimeException;

final class PriceScraper
{
    public function __construct(
        private readonly array $config
    ) {
    }

    public function fetchLatest(): array
    {
        $sourceUrl = $this->config['scraper']['source_url'];
        $html = $this->download($sourceUrl);
        $text = preg_replace('/\s+/', ' ', trim(strip_tags($html)));

        $pattern = '/OQD(?: Daily)? Marker Price\s+([A-Za-z]+ \d{1,2}, \d{4})\s+is\s+([0-9]+(?:\.[0-9]+)?)/i';

        if (!preg_match($pattern, $text, $matches)) {
            throw new RuntimeException('Unable to locate the OQD Marker Price on the source page.');
        }

        $priceDate = DateTimeImmutable::createFromFormat('F j, Y', trim($matches[1]));
        if ($priceDate === false) {
            throw new RuntimeException('Unable to parse the source date from the source page.');
        }

        return [
            'price_date' => $priceDate->format('Y-m-d'),
            'marker_price' => number_format((float) $matches[2], 2, '.', ''),
            'source_url' => $sourceUrl,
            'raw_label' => trim($matches[0]),
            'fetched_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ];
    }

    private function download(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required to fetch the source page.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT => $this->config['scraper']['user_agent'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Failed to download source page: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new RuntimeException('Source page returned HTTP ' . $statusCode . '.');
        }

        return $response;
    }
}