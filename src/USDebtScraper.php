<?php

declare(strict_types=1);

namespace OilApp;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class USDebtScraper
{
    private const SOURCE_URL = 'https://www.usdebtclock.org/';
    private const PRIMARY_LAYER = 'layer29';

    public function __construct(
        private readonly array $config
    ) {
    }

    public function fetchLatest(): array
    {
        $html = $this->download(self::SOURCE_URL);
        $elementId = $this->extractPrimaryElementId($html);
        [$baseAmount, $ratePerSecond] = $this->extractFormula($html, $elementId);

        $fetchedAt = new DateTimeImmutable('now');
        $value = $this->calculateCurrentValue($baseAmount, $ratePerSecond, $fetchedAt);

        return [
            'snapshot_date' => $fetchedAt->format('Y-m-d'),
            'debt_amount' => number_format($value, 2, '.', ''),
            'debt_rate_per_second' => number_format($ratePerSecond, 6, '.', ''),
            'source_url' => self::SOURCE_URL,
            'fetched_at' => $fetchedAt->format('Y-m-d H:i:s'),
            'source_layer' => self::PRIMARY_LAYER,
            'source_element_id' => $elementId,
            'raw_label' => sprintf('US National Debt from %s (%s)', self::PRIMARY_LAYER, $elementId),
        ];
    }

    private function extractPrimaryElementId(string $html): string
    {
        if (!preg_match('/<div id="' . self::PRIMARY_LAYER . '"><span id="([A-Za-z0-9]+)">/i', $html, $matches)) {
            throw new RuntimeException('Unable to locate the primary US debt element on usdebtclock.org.');
        }

        return $matches[1];
    }

    private function extractFormula(string $html, string $elementId): array
    {
        $quotedId = preg_quote($elementId, '/');
        $pattern = '/var\s+[A-Za-z0-9]+\s*=\s*(?:\/\*.*?\*\/\s*)?([0-9]{12,}(?:\.[0-9]+)?)\s*(?:\/\*.*?\*\/\s*)?;'
            . '.{0,220}?var\s+R3a45G7S\s*=\s*(?:\/\*.*?\*\/\s*)?([-.0-9]+)'
            . '.{0,600}?document\.getElementById\s*\(\s*[\'\"]' . $quotedId . '[\'\"]\s*\)/is';

        if (!preg_match($pattern, $html, $matches)) {
            throw new RuntimeException('Unable to extract the US debt formula from usdebtclock.org.');
        }

        return [(float) $matches[1], (float) $matches[2]];
    }

    private function calculateCurrentValue(float $baseAmount, float $ratePerSecond, DateTimeImmutable $fetchedAt): float
    {
        $secondsSinceMidnightUtc = $fetchedAt->setTimezone(new DateTimeZone('UTC'))->getTimestamp() % 86400;

        return $baseAmount + ($secondsSinceMidnightUtc * $ratePerSecond);
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
            throw new RuntimeException('Failed to download the US debt source page: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new RuntimeException('US debt source page returned HTTP ' . $statusCode . '.');
        }

        return $response;
    }
}
