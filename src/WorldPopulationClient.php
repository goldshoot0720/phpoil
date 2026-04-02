<?php

declare(strict_types=1);

namespace OilApp;

use RuntimeException;
use Throwable;

final class WorldPopulationClient
{
    private const MAIN_URL = 'https://www.worldometers.info/world-population/';
    private const HISTORICAL_URL = 'https://www.worldometers.info/world-population/world-population-by-year/';
    private const PROJECTIONS_URL = 'https://www.worldometers.info/world-population/world-population-projections/';

    public function __construct(
        private readonly array $config,
        private readonly string $fallbackPath
    ) {
    }

    public function load(): array
    {
        $warning = null;

        try {
            $overviewHtml = $this->request(self::MAIN_URL);
            $historicalHtml = $this->request(self::HISTORICAL_URL);
            $projectionHtml = $this->request(self::PROJECTIONS_URL);

            $overview = $this->parseOverview($overviewHtml);
            $historicalRows = $this->parseRows($historicalHtml);
            $projectionRows = $this->parseRows($projectionHtml);
            $mode = 'live';
        } catch (Throwable $exception) {
            $fallback = $this->loadFallback();
            $overview = $fallback['overview'];
            $historicalRows = $fallback['historical_rows'];
            $projectionRows = $fallback['projection_rows'];
            $mode = 'fallback';
            $warning = 'Worldometer live fetch failed, using bundled snapshot: ' . $exception->getMessage();
        }

        return $this->composeDataset($overview, $historicalRows, $projectionRows, $mode, $warning);
    }

    private function composeDataset(array $overview, array $historicalRows, array $projectionRows, string $mode, ?string $warning): array
    {
        $historicalMap = [];
        foreach ($historicalRows as $row) {
            $year = (int) ($row['year'] ?? 0);
            $population = (int) ($row['population'] ?? 0);
            if ($year >= 1950 && $year <= 2026 && $population > 0) {
                $historicalMap[$year] = $population;
            }
            if (in_array($year, [1900, 1927, 1950], true) && $population > 0) {
                $historicalMap[$year] = $population;
            }
        }

        foreach ([1900, 1927, 1950] as $requiredYear) {
            if (!isset($historicalMap[$requiredYear])) {
                throw new RuntimeException('Missing historical anchor year ' . $requiredYear . '.');
            }
        }

        $projectionMap = [];
        foreach ($projectionRows as $row) {
            $year = (int) ($row['year'] ?? 0);
            $population = (int) ($row['population'] ?? 0);
            if ($year >= 2026 && $year <= 2100 && $population > 0) {
                $projectionMap[$year] = $population;
            }
        }

        if (!isset($projectionMap[2100], $projectionMap[2050], $projectionMap[2026])) {
            throw new RuntimeException('Projection table is incomplete.');
        }

        $fullSeries = [];
        $fullSeries += $this->interpolateAnnual(1900, $historicalMap[1900], 1927, $historicalMap[1927]);
        $fullSeries += $this->interpolateAnnual(1927, $historicalMap[1927], 1950, $historicalMap[1950]);
        for ($year = 1950; $year <= 2026; $year++) {
            if (isset($historicalMap[$year])) {
                $fullSeries[$year] = $historicalMap[$year];
            }
        }
        for ($year = 2027; $year <= 2100; $year++) {
            if (isset($projectionMap[$year])) {
                $fullSeries[$year] = $projectionMap[$year];
            }
        }
        ksort($fullSeries);

        $scenarios = [
            'official' => $this->buildOfficialSeries($fullSeries),
            'momentum' => $this->simulateScenario($fullSeries, static function (int $year, float $baseDelta): float {
                if ($year <= 2040) {
                    return $baseDelta * 1.22;
                }
                if ($year <= 2070) {
                    return $baseDelta * 1.18;
                }
                return $baseDelta * 1.1 + 1_500_000;
            }),
            'balanced' => $this->simulateScenario($fullSeries, static function (int $year, float $baseDelta): float {
                if ($year <= 2040) {
                    return $baseDelta * 0.95;
                }
                if ($year <= 2075) {
                    return $baseDelta * 0.82;
                }
                return $baseDelta * 0.72 - 2_000_000;
            }),
            'aging' => $this->simulateScenario($fullSeries, static function (int $year, float $baseDelta): float {
                if ($year <= 2038) {
                    return $baseDelta * 0.82;
                }
                if ($year <= 2060) {
                    return $baseDelta * 0.52 - 4_000_000;
                }
                return $baseDelta * 0.28 - 8_000_000;
            }),
        ];

        $years = range(1900, 2100);
        $scenarioCards = [
            [
                'key' => 'official',
                'label' => 'Worldometer 中位線',
                'description' => '直接沿用 Worldometer 頁面上的歷史資料與 2026-2100 官方中位情境。',
                'accent' => '#155b70',
            ],
            [
                'key' => 'momentum',
                'label' => '動能延續',
                'description' => '假設城市化與壽命提升持續托住成長，下降斜率比官方更慢。',
                'accent' => '#c46d2d',
            ],
            [
                'key' => 'balanced',
                'label' => '平衡轉折',
                'description' => '假設教育與生育率下降更平均，世界人口提早趨於平台。',
                'accent' => '#4e8f5b',
            ],
            [
                'key' => 'aging',
                'label' => '快速老化',
                'description' => '假設部分地區提早進入低生育與高齡化，峰值提前後更早回落。',
                'accent' => '#b44343',
            ],
        ];

        foreach ($scenarioCards as &$card) {
            $series = $scenarios[$card['key']];
            $card['population_2050'] = $series[2050] ?? null;
            $card['population_2100'] = $series[2100] ?? null;
            $card['peak'] = $this->findPeak($series);
        }
        unset($card);

        $selectedYears = [1900, 1927, 1950, 2000, 2026, 2050, 2084, 2100];
        $tableRows = [];
        foreach ($selectedYears as $year) {
            $tableRows[] = [
                'year' => $year,
                'official' => $scenarios['official'][$year] ?? null,
                'momentum' => $scenarios['momentum'][$year] ?? null,
                'balanced' => $scenarios['balanced'][$year] ?? null,
                'aging' => $scenarios['aging'][$year] ?? null,
            ];
        }

        $liveHeadline = $overview['live_headline'] ?? 'World Population Clock';
        $title = $overview['title'] ?? $liveHeadline;
        $subtitle = $overview['subtitle'] ?? 'Worldometer page data with local scenario simulations.';
        $officialPeak = $this->findPeak($scenarios['official']);

        return [
            'mode' => $mode,
            'warning' => $warning,
            'title' => $title,
            'live_headline' => $liveHeadline,
            'subtitle' => $subtitle,
            'source_urls' => $overview['source_urls'] ?? [
                'main' => self::MAIN_URL,
                'historical' => self::HISTORICAL_URL,
                'projections' => self::PROJECTIONS_URL,
            ],
            'years' => $years,
            'series' => $scenarios,
            'scenario_cards' => $scenarioCards,
            'table_rows' => $tableRows,
            'anchors' => [
                'population_1900' => $scenarios['official'][1900],
                'population_2026' => $scenarios['official'][2026],
                'population_2050' => $scenarios['official'][2050],
                'population_2100' => $scenarios['official'][2100],
                'official_peak' => $officialPeak,
            ],
            'notes' => [
                'Worldometer historical table provides anchor rows for 1900, 1927, and annual data from 1950 onward.',
                '1900-1949 annual points on this page are interpolated locally between Worldometer anchor years. This interpolation is an inference, not an official Worldometer yearly series.',
                'The three extra scenario lines on this page are local simulations anchored to the Worldometer medium track, not official forecasts.',
            ],
        ];
    }

    private function buildOfficialSeries(array $fullSeries): array
    {
        $series = [];
        foreach (range(1900, 2100) as $year) {
            $series[$year] = $fullSeries[$year] ?? null;
        }

        return $series;
    }

    private function simulateScenario(array $officialSeries, callable $deltaTransformer): array
    {
        $series = [];
        foreach (range(1900, 2025) as $year) {
            $series[$year] = null;
        }

        $series[2026] = (float) $officialSeries[2026];

        for ($year = 2027; $year <= 2100; $year++) {
            $baseDelta = (float) $officialSeries[$year] - (float) $officialSeries[$year - 1];
            $delta = (float) $deltaTransformer($year, $baseDelta);
            $candidate = (float) $series[$year - 1] + $delta;
            $series[$year] = max(0.0, $candidate);
        }

        return $series;
    }

    private function findPeak(array $series): array
    {
        $peakYear = 0;
        $peakPopulation = -1.0;

        foreach ($series as $year => $population) {
            if ($population === null) {
                continue;
            }
            if ((float) $population > $peakPopulation) {
                $peakPopulation = (float) $population;
                $peakYear = (int) $year;
            }
        }

        return [
            'year' => $peakYear,
            'population' => (int) round($peakPopulation),
        ];
    }

    private function interpolateAnnual(int $startYear, int $startPopulation, int $endYear, int $endPopulation): array
    {
        $series = [];
        $span = $endYear - $startYear;
        if ($span <= 0) {
            return [$startYear => $startPopulation];
        }

        $ratio = $endPopulation / $startPopulation;
        $annualFactor = pow($ratio, 1 / $span);

        for ($offset = 0; $offset <= $span; $offset++) {
            $year = $startYear + $offset;
            $series[$year] = (int) round($startPopulation * pow($annualFactor, $offset));
        }

        $series[$endYear] = $endPopulation;

        return $series;
    }

    private function parseOverview(string $html): array
    {
        $title = 'World Population Clock - Worldometer';
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            $title = $this->clean($matches[1]);
        }

        $subtitle = 'Worldometer live page with historical and forecast tables sourced from the UN 2024 revision.';
        if (preg_match('/Population in the world is growing at a rate of around[^<]+/i', $html, $matches)) {
            $subtitle = $this->clean($matches[0]);
        }

        return [
            'title' => $title,
            'live_headline' => $title,
            'subtitle' => $subtitle,
            'source_urls' => [
                'main' => self::MAIN_URL,
                'historical' => self::HISTORICAL_URL,
                'projections' => self::PROJECTIONS_URL,
            ],
        ];
    }

    private function parseRows(string $html): array
    {
        preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $rowMatches);
        $rows = [];

        foreach ($rowMatches[1] as $rowHtml) {
            preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $rowHtml, $cellMatches);
            if (count($cellMatches[1]) < 2) {
                continue;
            }

            $cells = array_map(fn (string $cell): string => $this->clean($cell), $cellMatches[1]);
            $yearText = str_replace(',', '', $cells[0]);
            $populationText = str_replace(',', '', $cells[1]);

            if (!preg_match('/^\d{3,4}$/', $yearText) || !preg_match('/^\d+$/', $populationText)) {
                continue;
            }

            $rows[] = [
                'year' => (int) $yearText,
                'population' => (int) $populationText,
                'growth' => $cells[2] ?? null,
                'net_change' => $cells[3] ?? null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $a['year'] <=> $b['year']);

        return $rows;
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim((string) $value);
    }

    private function request(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required to fetch Worldometer data.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => $this->config['scraper']['user_agent'],
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Failed to download Worldometer page: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new RuntimeException('Worldometer returned HTTP ' . $statusCode . '.');
        }

        return (string) $response;
    }

    private function loadFallback(): array
    {
        if (!is_file($this->fallbackPath)) {
            throw new RuntimeException('Fallback dataset is missing.');
        }

        $decoded = json_decode((string) file_get_contents($this->fallbackPath), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Fallback dataset is invalid JSON.');
        }

        return $decoded;
    }
}
