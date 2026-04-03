<?php

declare(strict_types=1);

namespace OilApp;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class PizzintClient
{
    private const SOURCE_URL = 'https://www.pizzint.watch/';

    public function __construct(
        private readonly array $config,
        private readonly string $fallbackPath
    ) {
    }

    public function load(): array
    {
        $warning = null;

        try {
            $html = $this->request(self::SOURCE_URL);
            $payload = $this->parsePage($html);
            $mode = 'live';
        } catch (Throwable $exception) {
            $payload = $this->loadFallback();
            $mode = 'fallback';
            $warning = 'PizzINT live fetch failed, using bundled snapshot: ' . $exception->getMessage();
        }

        return $this->buildViewModel($payload, $mode, $warning);
    }

    private function buildViewModel(array $payload, string $mode, ?string $warning): array
    {
        $dashboard = $payload['dashboard'] ?? [];
        $places = is_array($dashboard['data'] ?? null) ? $dashboard['data'] : [];
        $locations = [];

        foreach ($places as $place) {
            if (!is_array($place)) {
                continue;
            }

            $sparkline = [];
            $activeDates = [];
            $activeWeeks = [];
            foreach (($place['sparkline_24h'] ?? []) as $point) {
                if (!is_array($point) || !array_key_exists('recorded_at', $point)) {
                    continue;
                }

                $timestamp = $this->formatTimestamp((string) $point['recorded_at']);
                $value = isset($point['current_popularity']) ? (int) $point['current_popularity'] : null;
                $sparkline[] = [
                    'label' => $timestamp['label'],
                    'value' => $value,
                ];

                if ($value !== null && $value > 0) {
                    $activeDates[$timestamp['date']] = true;
                    $activeWeeks[$timestamp['week']] = true;
                }
            }

            $latestObserved = null;
            $peak = 0;
            $sum = 0;
            $count = 0;
            foreach ($sparkline as $point) {
                if ($point['value'] === null) {
                    continue;
                }
                $latestObserved = $point['value'];
                $peak = max($peak, $point['value']);
                $sum += $point['value'];
                $count++;
            }

            $locations[] = [
                'place_id' => (string) ($place['place_id'] ?? ''),
                'name' => (string) ($place['name'] ?? 'Unknown location'),
                'address' => (string) ($place['address'] ?? ''),
                'current_popularity' => isset($place['current_popularity']) ? (int) $place['current_popularity'] : null,
                'percentage_of_usual' => isset($place['percentage_of_usual']) ? (int) $place['percentage_of_usual'] : null,
                'is_spike' => (bool) ($place['is_spike'] ?? false),
                'is_closed_now' => (bool) ($place['is_closed_now'] ?? false),
                'latest_observed' => $latestObserved,
                'peak_24h' => $peak,
                'avg_24h' => $count > 0 ? (int) round($sum / $count) : null,
                'day_streak' => $this->countConsecutiveDates(array_keys($activeDates)),
                'week_streak' => $this->countConsecutiveWeeks(array_keys($activeWeeks)),
                'active_days_in_sample' => count($activeDates),
                'active_weeks_in_sample' => count($activeWeeks),
                'baseline_popular_times' => is_array($place['baseline_popular_times'] ?? null) ? $place['baseline_popular_times'] : [],
                'sparkline_24h' => $sparkline,
            ];
        }

        usort($locations, static function (array $a, array $b): int {
            return ($b['peak_24h'] <=> $a['peak_24h']) ?: strcmp($a['name'], $b['name']);
        });

        $chartLocations = array_values(array_slice(array_filter($locations, static fn (array $location): bool => count($location['sparkline_24h']) > 0), 0, 6));
        $chartLabels = [];
        if ($chartLocations !== []) {
            $chartLabels = array_map(static fn (array $point): string => $point['label'], $chartLocations[0]['sparkline_24h']);
        }

        $lineDatasets = [];
        $palette = ['#ffb347', '#53c68c', '#66c7f4', '#ff7a7a', '#f2d15f', '#b988ff'];
        foreach ($chartLocations as $index => $location) {
            $lineDatasets[] = [
                'label' => $location['name'],
                'data' => array_map(static fn (array $point): ?int => $point['value'], $location['sparkline_24h']),
                'color' => $palette[$index % count($palette)],
            ];
        }

        $barLocations = array_values(array_slice($locations, 0, 8));
        $barLabels = array_map(static fn (array $location): string => $location['name'], $barLocations);
        $barValues = array_map(static fn (array $location): int => $location['latest_observed'] ?? 0, $barLocations);
        $barBaseline = array_map(static fn (array $location): int => $location['avg_24h'] ?? 0, $barLocations);

        $dayStreakLocations = $locations;
        usort($dayStreakLocations, static function (array $a, array $b): int {
            return ($b['day_streak'] <=> $a['day_streak'])
                ?: ($b['active_days_in_sample'] <=> $a['active_days_in_sample'])
                ?: ($b['peak_24h'] <=> $a['peak_24h'])
                ?: strcmp($a['name'], $b['name']);
        });
        $dayStreakLocations = array_values(array_slice($dayStreakLocations, 0, 8));

        $weekStreakLocations = $locations;
        usort($weekStreakLocations, static function (array $a, array $b): int {
            return ($b['week_streak'] <=> $a['week_streak'])
                ?: ($b['active_weeks_in_sample'] <=> $a['active_weeks_in_sample'])
                ?: ($b['peak_24h'] <=> $a['peak_24h'])
                ?: strcmp($a['name'], $b['name']);
        });
        $weekStreakLocations = array_values(array_slice($weekStreakLocations, 0, 8));

        $defconLevel = (int) ($dashboard['defcon_level'] ?? 4);
        $overallIndex = (int) round((float) ($dashboard['overall_index'] ?? 0));
        $details = is_array($dashboard['defcon_details'] ?? null) ? $dashboard['defcon_details'] : [];
        $activeSpikes = is_array($dashboard['active_spikes'] ?? null) ? $dashboard['active_spikes'] : [];
        $locationsMonitored = $this->extractLocationsMonitored((string) ($payload['html'] ?? '')) ?: count($locations);
        $maxDayStreak = $locations === [] ? 0 : max(array_map(static fn (array $location): int => (int) $location['day_streak'], $locations));
        $maxWeekStreak = $locations === [] ? 0 : max(array_map(static fn (array $location): int => (int) $location['week_streak'], $locations));

        return [
            'mode' => $mode,
            'warning' => $warning,
            'title' => (string) ($payload['title'] ?? 'PizzINT Watch'),
            'source_url' => (string) ($payload['source_url'] ?? self::SOURCE_URL),
            'fetched_at' => (string) ($payload['fetched_at'] ?? ($dashboard['timestamp'] ?? '')),
            'defcon_level' => $defconLevel,
            'defcon_label' => $this->defconLabel($defconLevel),
            'overall_index' => $overallIndex,
            'locations_monitored' => $locationsMonitored,
            'active_spike_count' => count($activeSpikes),
            'open_places' => (int) ($details['open_places'] ?? 0),
            'max_day_streak' => $maxDayStreak,
            'max_week_streak' => $maxWeekStreak,
            'line_chart' => [
                'labels' => $chartLabels,
                'datasets' => $lineDatasets,
            ],
            'bar_chart' => [
                'labels' => $barLabels,
                'latest' => $barValues,
                'baseline' => $barBaseline,
            ],
            'day_streak_chart' => [
                'labels' => array_map(static fn (array $location): string => $location['name'], $dayStreakLocations),
                'values' => array_map(static fn (array $location): int => (int) $location['day_streak'], $dayStreakLocations),
            ],
            'week_streak_chart' => [
                'labels' => array_map(static fn (array $location): string => $location['name'], $weekStreakLocations),
                'values' => array_map(static fn (array $location): int => (int) $location['week_streak'], $weekStreakLocations),
            ],
            'locations' => $barLocations,
            'notes' => [
                'PizzINT says it uses public Google Maps Popular Times signals and compares them to historical baselines, with updates around every 10 minutes.',
                'This page parses the site-embedded initialDashboardData payload from the current HTML response, then falls back to a bundled snapshot if the live fetch fails.',
                'The chart here shows the latest 24-hour sparkline values already embedded by PizzINT, not an independently scraped Google Maps feed.',
                'Consecutive day and week streaks are inferred from the current 24-hour sample only. A day counts if any sample is above zero in that calendar day; a week counts if any active day falls in that ISO week.',
            ],
        ];
    }

    private function countConsecutiveDates(array $dates): int
    {
        $dates = array_values(array_unique(array_filter($dates)));
        if ($dates === []) {
            return 0;
        }

        rsort($dates, SORT_STRING);
        $streak = 1;
        $cursor = new DateTimeImmutable($dates[0]);

        for ($i = 1, $count = count($dates); $i < $count; $i++) {
            $expected = $cursor->sub(new DateInterval('P1D'))->format('Y-m-d');
            if ($dates[$i] !== $expected) {
                break;
            }

            $streak++;
            $cursor = new DateTimeImmutable($dates[$i]);
        }

        return $streak;
    }

    private function countConsecutiveWeeks(array $weeks): int
    {
        $weeks = array_values(array_unique(array_filter($weeks)));
        if ($weeks === []) {
            return 0;
        }

        rsort($weeks, SORT_STRING);
        $cursor = $this->weekKeyToDate($weeks[0]);
        if ($cursor === null) {
            return 0;
        }

        $streak = 1;
        for ($i = 1, $count = count($weeks); $i < $count; $i++) {
            $cursor = $cursor->sub(new DateInterval('P7D'));
            $expected = $cursor->format('o-\\WW');
            if ($weeks[$i] !== $expected) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    private function weekKeyToDate(string $weekKey): ?DateTimeImmutable
    {
        if (!preg_match('/^(\d{4})-W(\d{2})$/', $weekKey, $matches)) {
            return null;
        }

        return (new DateTimeImmutable('now', new DateTimeZone($this->config['timezone'])))
            ->setISODate((int) $matches[1], (int) $matches[2]);
    }

    private function defconLabel(int $level): string
    {
        return match ($level) {
            1 => 'Pizza Panic',
            2 => 'Red Crust',
            3 => 'Hot Slice',
            4 => 'Double Take',
            default => 'Routine Slice',
        };
    }

    private function extractLocationsMonitored(string $html): ?int
    {
        if ($html !== '' && preg_match('/(\d+)\s+LOCATIONS MONITORED/i', $html, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function parsePage(string $html): array
    {
        $title = 'PizzINT';
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            $title = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $escapedJson = $this->extractEscapedJsonObject($html, 'initialDashboardData\\":');
        $json = json_decode('"' . $escapedJson . '"', true);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to unescape PizzINT dashboard payload.');
        }

        $dashboard = json_decode($json, true);
        if (!is_array($dashboard)) {
            throw new RuntimeException('PizzINT dashboard payload is not valid JSON.');
        }

        return [
            'title' => $title,
            'source_url' => self::SOURCE_URL,
            'fetched_at' => (string) ($dashboard['timestamp'] ?? ''),
            'dashboard' => $dashboard,
            'html' => $html,
        ];
    }

    private function extractEscapedJsonObject(string $html, string $needle): string
    {
        $start = strpos($html, $needle);
        if ($start === false) {
            throw new RuntimeException('initialDashboardData marker not found.');
        }

        $start = strpos($html, '{', $start);
        if ($start === false) {
            throw new RuntimeException('initialDashboardData object start not found.');
        }

        $length = strlen($html);
        $inString = false;
        $level = 0;

        for ($i = $start; $i < $length; $i++) {
            $char = $html[$i];
            $next = $i + 1 < $length ? $html[$i + 1] : '';

            if ($char === '\\' && $next === '"') {
                $inString = !$inString;
                $i++;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{') {
                $level++;
            } elseif ($char === '}') {
                $level--;
                if ($level === 0) {
                    return substr($html, $start, $i - $start + 1);
                }
            }
        }

        throw new RuntimeException('initialDashboardData object end not found.');
    }

    private function formatTimestamp(string $timestamp): array
    {
        try {
            $date = new DateTimeImmutable($timestamp);
            $date = $date->setTimezone(new DateTimeZone($this->config['timezone']));

            return [
                'label' => $date->format('m-d H:i'),
                'date' => $date->format('Y-m-d'),
                'week' => $date->format('o-\\WW'),
            ];
        } catch (Throwable) {
            return ['label' => $timestamp, 'date' => '', 'week' => ''];
        }
    }

    private function request(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required to fetch PizzINT data.');
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
            throw new RuntimeException('Failed to download PizzINT page: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new RuntimeException('PizzINT returned HTTP ' . $statusCode . '.');
        }

        return (string) $response;
    }

    private function loadFallback(): array
    {
        if (!is_file($this->fallbackPath)) {
            throw new RuntimeException('PizzINT fallback dataset is missing.');
        }

        $decoded = json_decode((string) file_get_contents($this->fallbackPath), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('PizzINT fallback dataset is invalid JSON.');
        }

        return $decoded;
    }
}
