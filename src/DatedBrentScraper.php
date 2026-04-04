<?php

declare(strict_types=1);

namespace OilApp;

use DateTimeImmutable;
use RuntimeException;

final class DatedBrentScraper
{
    private const DEFAULT_SOURCE_URL = 'https://datahub.io/core/oil-prices/_r/-/data/brent-daily.csv';

    public function __construct(
        private readonly array $config
    ) {
    }

    public function fetchHistory(int $limit = 365): array
    {
        $sourceUrl = $this->config['dated_brent']['source_url'] ?? self::DEFAULT_SOURCE_URL;
        $csv = $this->download($sourceUrl);
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));

        if (!$lines || count($lines) < 2) {
            throw new RuntimeException('Unable to load Dated Brent history from source.');
        }

        $records = [];
        foreach (array_slice($lines, 1) as $line) {
            if ($line === '') {
                continue;
            }

            $columns = str_getcsv($line);
            if (count($columns) < 2 || $columns[1] === '.') {
                continue;
            }

            $date = DateTimeImmutable::createFromFormat('Y-m-d', trim($columns[0]));
            if ($date === false) {
                continue;
            }

            $price = (float) trim($columns[1]);
            $records[] = [
                'price_date' => $date->format('Y-m-d'),
                'spot_price' => number_format($price, 2, '.', ''),
                'source_url' => $sourceUrl,
                'raw_label' => sprintf('Dated Brent Spot Price FOB (%s): %s', $date->format('Y-m-d'), number_format($price, 2, '.', '')),
                'fetched_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ];
        }

        if ($records === []) {
            throw new RuntimeException('Dated Brent source returned no usable observations.');
        }

        if ($limit > 0 && count($records) > $limit) {
            $records = array_slice($records, -$limit);
        }

        return $records;
    }

    private function download(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required to fetch the Dated Brent source.');
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
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Failed to download Dated Brent source: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new RuntimeException('Dated Brent source returned HTTP ' . $statusCode . '.');
        }

        return $response;
    }
}
