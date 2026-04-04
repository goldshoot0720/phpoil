<?php

declare(strict_types=1);

namespace OilApp;

use DateTimeImmutable;
use RuntimeException;

final class DramDdr5PriceScraper
{
    private const DEFAULT_SOURCE_URL = 'https://www.trendforce.com/price/dram/dram_spot';
    private const DEFAULT_ITEM_NAME = 'DDR5 UDIMM 16GB 4800/5600';

    public function __construct(
        private readonly array $config
    ) {
    }

    public function fetchLatest(): array
    {
        $sourceUrl = $this->config['dram']['source_url'] ?? self::DEFAULT_SOURCE_URL;
        $itemName = $this->config['dram']['ddr5_16gb_item_name'] ?? self::DEFAULT_ITEM_NAME;
        $html = $this->download($sourceUrl);
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($plain));

        if (!is_string($text) || $text === '') {
            throw new RuntimeException('Unable to parse DRAM source text.');
        }

        $updatePattern = '/Module Spot Price\s+Last Update\s+(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})\s+\(GMT\+8\)/u';
        if (!preg_match($updatePattern, $text, $updateMatches)) {
            throw new RuntimeException('Unable to locate the Module Spot Price update time.');
        }

        $quotedItem = preg_quote($itemName, '/');
        $itemPattern = '/'.$quotedItem.'\s+([0-9,]+(?:\.[0-9]+)?)\s+([0-9,]+(?:\.[0-9]+)?)\s+([0-9,]+(?:\.[0-9]+)?)\s+([0-9,]+(?:\.[0-9]+)?)\s+([0-9,]+(?:\.[0-9]+)?)\s+(?:▲|▼|—)?\s*([+\-]?[0-9]+(?:\.[0-9]+)?)\s*%/u';
        if (!preg_match($itemPattern, $text, $itemMatches)) {
            throw new RuntimeException('Unable to locate the DDR5 16GB module row on the source page.');
        }

        $snapshotDate = DateTimeImmutable::createFromFormat('Y-m-d H:i', $updateMatches[1] . ' ' . $updateMatches[2]);
        if ($snapshotDate === false) {
            throw new RuntimeException('Unable to parse the DDR5 16GB update timestamp.');
        }

        $cleanNumber = static fn(string $value): string => str_replace(',', '', trim($value));
        $averageValue = (float) $cleanNumber($itemMatches[5]);

        return [
            'snapshot_date' => $snapshotDate->format('Y-m-d'),
            'item_name' => $itemName,
            'weekly_high' => number_format((float) $cleanNumber($itemMatches[1]), 2, '.', ''),
            'weekly_low' => number_format((float) $cleanNumber($itemMatches[2]), 2, '.', ''),
            'session_high' => number_format((float) $cleanNumber($itemMatches[3]), 2, '.', ''),
            'session_low' => number_format((float) $cleanNumber($itemMatches[4]), 2, '.', ''),
            'session_average' => number_format($averageValue, 3, '.', ''),
            'average_change' => number_format((float) $cleanNumber($itemMatches[6]), 2, '.', ''),
            'source_url' => $sourceUrl,
            'fetched_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            'raw_label' => sprintf('%s %s average %s', $itemName, $snapshotDate->format('Y-m-d'), number_format($averageValue, 3, '.', '')),
        ];
    }

    private function download(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required to fetch the DRAM source.');
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
            throw new RuntimeException('Failed to download DRAM source: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new RuntimeException('DRAM source returned HTTP ' . $statusCode . '.');
        }

        return $response;
    }
}