<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\Database;
use OilApp\DramDdr5PriceRepository;
use OilApp\DramDdr5PriceScraper;
use OilApp\DramDdr5PriceService;

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'dram_ddr5_16gb';

$connection = Database::connect($config);
$pdo = $connection['pdo'];
$driver = $connection['driver'];
$warning = $connection['warning'];
Database::ensureSchema($pdo, $driver);

$repository = new DramDdr5PriceRepository($pdo, $driver);
$messages = [];
$errors = [];

if (isset($_GET['message']) && $_GET['message'] !== '') {
    $messages[] = (string) $_GET['message'];
}
if (isset($_GET['error']) && $_GET['error'] !== '') {
    $errors[] = (string) $_GET['error'];
}
if ($warning) {
    $messages[] = $warning;
}

$today = date('Y-m-d');
$autoSyncAt = strtotime($today . ' 14:40:00');
$shouldAutoSync = time() >= $autoSyncAt && $repository->latestFetchDate() !== $today;

if ($shouldAutoSync) {
    try {
        $service = new DramDdr5PriceService(
            new DramDdr5PriceScraper($config),
            $repository
        );
        $record = $service->fetchAndStore();
        $messages[] = sprintf('Synced DDR5 16GB module spot for %s: %s', $record['snapshot_date'], $record['session_average']);
    } catch (Throwable $exception) {
        $errors[] = 'DDR5 16GB sync failed: ' . $exception->getMessage();
    }
}

$rows = $repository->all();
$latest = $repository->latest();
$recentLabels = array_map(static fn(array $row): string => $row['snapshot_date'], $rows);
$recentAverages = array_map(static fn(array $row): float => (float) $row['session_average'], $rows);
$recentWeeklyHighs = array_map(static fn(array $row): float => (float) $row['weekly_high'], $rows);
$recentWeeklyLows = array_map(static fn(array $row): float => (float) $row['weekly_low'], $rows);

$years = range(2016, 2026);
$grouped = [];
foreach ($rows as $row) {
    $year = substr((string) $row['snapshot_date'], 0, 4);
    $grouped[$year][] = (float) $row['session_average'];
}

$annualLabels = array_map(static fn(int $year): string => (string) $year, $years);
$annualAverages = [];
foreach ($years as $year) {
    $key = (string) $year;
    if (!isset($grouped[$key])) {
        $annualAverages[] = null;
        continue;
    }

    $annualAverages[] = round(array_sum($grouped[$key]) / count($grouped[$key]), 3);
}

$latestAverage = $latest ? number_format((float) $latest['session_average'], 3) : '--';
$latestChange = $latest ? number_format((float) $latest['average_change'], 2) : '--';
$latestDate = $latest['snapshot_date'] ?? 'Not synced yet';
$latestWeeklyRange = $latest ? number_format((float) $latest['weekly_low'], 2) . ' - ' . number_format((float) $latest['weekly_high'], 2) : '--';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DDR5 16GB Price</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #f3eee4;
            --panel: rgba(255, 252, 247, 0.94);
            --ink: #1f2a30;
            --muted: #58636c;
            --accent: #8d5b17;
            --accent-soft: rgba(141, 91, 23, 0.14);
            --teal: #1b7a69;
            --blue: #325f9f;
            --danger: #b44343;
            --shadow: 0 18px 48px rgba(31, 42, 48, 0.12);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Noto Sans TC", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(141, 91, 23, 0.18), transparent 28%),
                linear-gradient(135deg, #efe2c7 0%, #f8f4ec 54%, #e0ebf0 100%);
            min-height: 100vh;
        }
        .shell { max-width: 1280px; margin: 0 auto; padding: 36px 20px 52px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 252, 246, 0.75); border: 1px solid rgba(31, 42, 48, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #fff; }
        .notice { margin-bottom: 16px; padding: 14px 18px; border-radius: 16px; font-weight: 600; }
        .notice.ok { background: rgba(27, 122, 105, 0.12); color: #0f5d50; }
        .notice.error { background: rgba(180, 67, 67, 0.12); color: var(--danger); }
        .hero, .grid, .meta-grid { display: grid; gap: 24px; }
        .hero { grid-template-columns: 1.2fr 0.95fr; margin-bottom: 24px; }
        .grid { grid-template-columns: 1.15fr 1fr; }
        .meta-grid { grid-template-columns: 1fr 1fr; margin-top: 24px; }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 24px; box-shadow: var(--shadow); padding: 24px; backdrop-filter: blur(10px); }
        .pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 12px 18px; font-size: 0.92rem; font-weight: 800; background: var(--accent-soft); color: var(--accent); letter-spacing: 0.05em; text-transform: uppercase; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { font-size: clamp(2.4rem, 5vw, 4.5rem); line-height: 0.96; margin-bottom: 16px; max-width: 9ch; }
        .lead, .small { color: var(--muted); line-height: 1.75; }
        .metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 18px; }
        .metric-box { padding: 16px; border-radius: 20px; background: rgba(31, 42, 48, 0.04); }
        .metric-label { color: var(--muted); font-size: 0.82rem; letter-spacing: 0.08em; text-transform: uppercase; }
        .metric-value { margin-top: 8px; font-size: clamp(1.2rem, 2vw, 2rem); font-weight: 800; }
        .flash { margin-top: 16px; padding: 14px 16px; border-radius: 18px; background: rgba(50, 95, 159, 0.10); color: #274c82; font-weight: 700; line-height: 1.65; }
        .chart-wrap { min-height: 360px; position: relative; }
        .list { margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.8; }
        .source-links { display: grid; gap: 10px; margin-top: 14px; }
        .source-links a { color: var(--accent); text-decoration: none; font-weight: 700; }
        .eyebrow { font-size: 0.82rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: var(--accent); margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid rgba(31, 42, 48, 0.08); }
        th { color: var(--muted); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .tag-up { color: #0f7a57; font-weight: 800; }
        .tag-down { color: #b44343; font-weight: 800; }
        .tag-flat { color: var(--muted); font-weight: 800; }
        .empty-state { padding: 18px; border-radius: 18px; background: rgba(31, 42, 48, 0.04); color: var(--muted); }
        @media (max-width: 1020px) {
            .hero, .grid, .meta-grid, .metric-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 860px) {
            .topbar { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Oil Price Monitor</div>
        <nav class="nav">
            <a class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/') ?>">首頁</a>
            <a class="<?= $currentPage === 'dram_spot' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/dram_spot.php') ?>">DRAM Spot</a>
            <a class="<?= $currentPage === 'dram_ddr5_16gb' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/dram_ddr5_16gb.php') ?>">DDR5 16GB</a>
            <a class="<?= $currentPage === 'commits' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits 統計</a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/settings.php') ?>">GitHub Token</a>
        </nav>
    </div>

    <?php foreach ($messages as $message): ?>
        <div class="notice ok"><?= htmlspecialchars($message) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <div class="notice error"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <section class="hero">
        <div class="card">
            <div class="pill">DDR5 16GB price</div>
            <h1>DDR5 16GB<br>2016-2026 chart</h1>
            <p class="lead">This page tracks the public TrendForce module spot quote for <strong>DDR5 UDIMM 16GB 4800/5600</strong>. The annual chart is pinned to 2016-2026, while the lower trend chart keeps extending as new snapshots are stored.</p>
            <div class="metric-grid">
                <div class="metric-box">
                    <div class="metric-label">Latest Average</div>
                    <div class="metric-value"><?= htmlspecialchars($latestAverage) ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Snapshot Date</div>
                    <div class="metric-value" style="font-size: 1.15rem;"><?= htmlspecialchars($latestDate) ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Weekly Range</div>
                    <div class="metric-value" style="font-size: 1.15rem;"><?= htmlspecialchars($latestWeeklyRange) ?></div>
                </div>
            </div>
            <div class="flash">The chart keeps updating from the TrendForce module spot board. Years before public DDR5 module listings remain blank on purpose instead of being filled with guessed values.</div>
        </div>

        <div class="card">
            <div class="eyebrow">Quick Read</div>
            <ul class="list">
                <li>Tracking target: DDR5 UDIMM 16GB 4800/5600.</li>
                <li>Latest average change: <?= htmlspecialchars($latestChange) ?>%.</li>
                <li>Year axis is locked from 2016 through 2026 for easier long-range comparison.</li>
                <li>Daily cron sync will keep appending new snapshots to the lower chart and annual rollup.</li>
            </ul>
            <div class="source-links">
                <a href="https://www.trendforce.com/price/dram/dram_spot" target="_blank" rel="noopener">TrendForce DRAM price board</a>
                <a href="https://www.trendforce.com/price" target="_blank" rel="noopener">TrendForce Price Trends</a>
            </div>
        </div>
    </section>

    <section class="grid">
        <div class="card">
            <h2>Annual Average Range (2016-2026)</h2>
            <div class="chart-wrap">
                <canvas id="annualChart" height="140"></canvas>
            </div>
        </div>

        <div class="card">
            <h2>Why Early Years May Be Blank</h2>
            <p class="small">DDR5 public module spot quotes appeared later than 2016. So this page keeps the full 2016-2026 frame but leaves years without stored observations empty instead of fabricating back-history.</p>
            <ul class="list">
                <li>That keeps the timeline honest.</li>
                <li>As new snapshots accumulate, the 2026 series and future years will become more informative.</li>
                <li>If you want full backfill later, we can add an archive-import task when a historical source is available.</li>
            </ul>
        </div>
    </section>

    <section class="meta-grid">
        <div class="card">
            <h2>Continuous Update Trend</h2>
            <?php if ($rows === []): ?>
                <div class="empty-state">No DDR5 16GB snapshots have been stored yet. Once the sync runs successfully, the line chart and annual rollup will populate automatically.</div>
            <?php else: ?>
                <div class="chart-wrap">
                    <canvas id="recentChart" height="140"></canvas>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Snapshot Table</h2>
            <?php if ($rows === []): ?>
                <div class="empty-state">No records yet.</div>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Weekly High</th>
                        <th>Weekly Low</th>
                        <th>Average</th>
                        <th>Change</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_reverse($rows) as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['snapshot_date']) ?></td>
                            <td><?= htmlspecialchars(number_format((float) $row['weekly_high'], 2)) ?></td>
                            <td><?= htmlspecialchars(number_format((float) $row['weekly_low'], 2)) ?></td>
                            <td><?= htmlspecialchars(number_format((float) $row['session_average'], 3)) ?></td>
                            <td>
                                <?php if ((float) $row['average_change'] > 0): ?>
                                    <span class="tag-up">+<?= htmlspecialchars(number_format((float) $row['average_change'], 2)) ?>%</span>
                                <?php elseif ((float) $row['average_change'] < 0): ?>
                                    <span class="tag-down"><?= htmlspecialchars(number_format((float) $row['average_change'], 2)) ?>%</span>
                                <?php else: ?>
                                    <span class="tag-flat">0.00%</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
const annualLabels = <?= json_encode($annualLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const annualAverages = <?= json_encode($annualAverages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const recentLabels = <?= json_encode($recentLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const recentAverages = <?= json_encode($recentAverages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const recentWeeklyHighs = <?= json_encode($recentWeeklyHighs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const recentWeeklyLows = <?= json_encode($recentWeeklyLows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

new Chart(document.getElementById('annualChart'), {
    type: 'line',
    data: {
        labels: annualLabels,
        datasets: [{
            label: 'Annual Average',
            data: annualAverages,
            borderColor: '#8d5b17',
            backgroundColor: 'rgba(141, 91, 23, 0.16)',
            borderWidth: 3,
            tension: 0.28,
            fill: true,
            spanGaps: false,
            pointRadius: 4,
            pointHoverRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true }
        },
        scales: {
            y: { beginAtZero: true },
            x: { ticks: { maxRotation: 0 } }
        }
    }
});

if (document.getElementById('recentChart')) {
    new Chart(document.getElementById('recentChart'), {
        type: 'line',
        data: {
            labels: recentLabels,
            datasets: [
                {
                    label: 'Session Average',
                    data: recentAverages,
                    borderColor: '#1b7a69',
                    backgroundColor: 'rgba(27, 122, 105, 0.14)',
                    borderWidth: 3,
                    tension: 0.24,
                    fill: true,
                    pointRadius: 3
                },
                {
                    label: 'Weekly High',
                    data: recentWeeklyHighs,
                    borderColor: '#325f9f',
                    borderWidth: 2,
                    tension: 0.18,
                    pointRadius: 2
                },
                {
                    label: 'Weekly Low',
                    data: recentWeeklyLows,
                    borderColor: '#b44343',
                    borderWidth: 2,
                    tension: 0.18,
                    pointRadius: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}
</script>
</body>
</html>