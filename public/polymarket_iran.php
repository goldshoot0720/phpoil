<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'polymarket_iran';

$snapshotAt = '2026-04-04 12:58 Asia/Taipei';
$totalVolume = 115485413;
$marketOpen = '2026-03-31 on-screen snapshot';
$sourceUrl = 'https://polymarket.com/zh/event/us-forces-enter-iran-by';
$outcomes = [
    [
        'label' => '2026-03-31',
        'probability' => 0.1,
        'volume' => 72523431,
        'yes_price' => '0.1c',
        'no_price' => '0.0c',
        'status' => 'Under review / disputed',
    ],
    [
        'label' => '2026-04-30',
        'probability' => 82,
        'volume' => 30254847,
        'yes_price' => '82c',
        'no_price' => '19c',
        'status' => 'Active',
    ],
    [
        'label' => '2026-12-31',
        'probability' => 90,
        'volume' => 10647874,
        'yes_price' => '90c',
        'no_price' => '11c',
        'status' => 'Active',
    ],
];

$labels = array_map(static fn(array $outcome): string => $outcome['label'], $outcomes);
$probabilities = array_map(static fn(array $outcome): float => (float) $outcome['probability'], $outcomes);
$volumes = array_map(static fn(array $outcome): float => round($outcome['volume'] / 1000000, 2), $outcomes);
$leader = $outcomes[2];
$runnerUp = $outcomes[1];
$aprilOutcome = $outcomes[1];
$aprilYes = (float) $aprilOutcome['probability'];
$aprilNo = 100 - $aprilYes;
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Polymarket Iran Event</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #f4efe6;
            --panel: rgba(255, 252, 246, 0.94);
            --ink: #1f2a30;
            --muted: #59656c;
            --accent: #8d5b17;
            --accent-soft: rgba(141, 91, 23, 0.14);
            --teal: #1b7a69;
            --blue: #325f9f;
            --danger: #a13e3e;
            --danger-soft: rgba(161, 62, 62, 0.12);
            --shadow: 0 18px 50px rgba(31, 42, 48, 0.12);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Noto Sans TC", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(141, 91, 23, 0.18), transparent 28%),
                linear-gradient(135deg, #efe2c7 0%, #f8f4ec 55%, #e3ebf0 100%);
            min-height: 100vh;
        }
        .shell { max-width: 1260px; margin: 0 auto; padding: 36px 20px 52px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 252, 246, 0.75); border: 1px solid rgba(31, 42, 48, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #fff; }
        .hero, .grid, .meta-grid { display: grid; gap: 24px; }
        .hero { grid-template-columns: 1.2fr 0.95fr; margin-bottom: 24px; }
        .grid { grid-template-columns: 1.25fr 1fr; }
        .meta-grid { grid-template-columns: 1fr 1fr; margin-top: 24px; }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 24px; box-shadow: var(--shadow); padding: 24px; backdrop-filter: blur(10px); }
        .pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 12px 18px; font-size: 0.92rem; font-weight: 800; background: var(--accent-soft); color: var(--accent); text-transform: uppercase; letter-spacing: 0.05em; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { font-size: clamp(2.3rem, 5vw, 4.4rem); line-height: 0.96; margin-bottom: 16px; max-width: 10ch; }
        .lead, .small { color: var(--muted); line-height: 1.75; }
        .metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 18px; }
        .metric-box { padding: 16px; border-radius: 20px; background: rgba(31, 42, 48, 0.04); }
        .metric-label { color: var(--muted); font-size: 0.82rem; letter-spacing: 0.08em; text-transform: uppercase; }
        .metric-value { margin-top: 8px; font-size: clamp(1.2rem, 2vw, 2rem); font-weight: 800; }
        .flash { margin-top: 16px; padding: 14px 16px; border-radius: 18px; background: rgba(50, 95, 159, 0.10); color: #274c82; font-weight: 700; line-height: 1.65; }
        .chart-wrap { min-height: 360px; position: relative; }
        .chart-wrap.compact { min-height: 280px; }
        .april-meter {
            display: grid;
            gap: 16px;
        }
        .april-band {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            overflow: hidden;
            border-radius: 20px;
            background: rgba(31, 42, 48, 0.05);
            min-height: 54px;
        }
        .april-band-yes {
            display: flex;
            align-items: center;
            padding: 0 18px;
            background: rgba(27, 122, 105, 0.18);
            color: #0f5d50;
            font-weight: 800;
        }
        .april-band-no {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 18px;
            background: var(--danger-soft);
            color: var(--danger);
            font-weight: 800;
        }
        .list { margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.8; }
        .source-links { display: grid; gap: 10px; margin-top: 14px; }
        .source-links a { color: var(--accent); text-decoration: none; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid rgba(31, 42, 48, 0.08); }
        th { color: var(--muted); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .status-open { color: var(--teal); font-weight: 800; }
        .status-review { color: var(--danger); font-weight: 800; }
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
            <a class="<?= $currentPage === 'polymarket_iran' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/polymarket_iran.php') ?>">Polymarket Iran</a>
            <a class="<?= $currentPage === 'commits' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits 統計</a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/settings.php') ?>">GitHub Token</a>
        </nav>
    </div>

    <section class="hero">
        <div class="card">
            <div class="pill">Polymarket snapshot</div>
            <h1>US forces enter Iran by...?</h1>
            <p class="lead">This page is a station dashboard for the Polymarket event snapshot at <strong><?= htmlspecialchars($snapshotAt) ?></strong>. It summarizes the leading date buckets, total market activity, and the rule framing shown on Polymarket.</p>
            <div class="metric-grid">
                <div class="metric-box">
                    <div class="metric-label">Leading Outcome</div>
                    <div class="metric-value"><?= htmlspecialchars($leader['label']) ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">4/30 Probability</div>
                    <div class="metric-value"><?= htmlspecialchars(number_format($aprilYes, 0)) ?>%</div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Total Volume</div>
                    <div class="metric-value">$<?= htmlspecialchars(number_format($totalVolume)) ?></div>
                </div>
            </div>
            <div class="flash">At this snapshot, <strong>2026-04-30</strong> is priced at <strong><?= htmlspecialchars(number_format($aprilYes, 0)) ?>%</strong>, with buy yes at <strong><?= htmlspecialchars($aprilOutcome['yes_price']) ?></strong> and buy no at <strong><?= htmlspecialchars($aprilOutcome['no_price']) ?></strong>.</div>
        </div>

        <div class="card">
            <h2>Rules Snapshot</h2>
            <ul class="list">
                <li>The market resolves Yes only if active US military personnel physically enter Iran's terrestrial territory by the listed date in ET.</li>
                <li>Maritime or aerial entry does not count.</li>
                <li>Military special operations count, but intelligence operatives do not.</li>
                <li>Contractors, advisors, or diplomatic visits by senior service members do not qualify.</li>
                <li>Resolution source is a consensus of credible reporting.</li>
            </ul>
            <div class="source-links">
                <a href="<?= htmlspecialchars($sourceUrl) ?>" target="_blank" rel="noopener">Open Polymarket source</a>
                <a href="https://polymarket.com/zh" target="_blank" rel="noopener">Polymarket zh</a>
            </div>
        </div>
    </section>

    <section class="grid">
        <div class="card">
            <h2>Probability Snapshot</h2>
            <div class="chart-wrap">
                <canvas id="probabilityChart" height="136"></canvas>
            </div>
        </div>

        <div class="card">
            <h2>4月30日 機率圖表</h2>
            <div class="april-meter">
                <div class="april-band">
                    <div class="april-band-yes" style="width: <?= htmlspecialchars((string) $aprilYes) ?>%;">Yes <?= htmlspecialchars(number_format($aprilYes, 0)) ?>%</div>
                    <div class="april-band-no">No <?= htmlspecialchars(number_format($aprilNo, 0)) ?>%</div>
                </div>
                <div class="chart-wrap compact">
                    <canvas id="aprilChart" height="120"></canvas>
                </div>
            </div>
        </div>
    </section>

    <section class="meta-grid">
        <div class="card">
            <h2>Outcome Table</h2>
            <table>
                <thead>
                <tr>
                    <th>Date Bucket</th>
                    <th>Probability</th>
                    <th>Volume</th>
                    <th>Buy Yes</th>
                    <th>Buy No</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($outcomes as $outcome): ?>
                    <tr>
                        <td><?= htmlspecialchars($outcome['label']) ?></td>
                        <td><?= htmlspecialchars(number_format((float) $outcome['probability'], 1)) ?>%</td>
                        <td>$<?= htmlspecialchars(number_format((float) $outcome['volume'])) ?></td>
                        <td><?= htmlspecialchars($outcome['yes_price']) ?></td>
                        <td><?= htmlspecialchars($outcome['no_price']) ?></td>
                        <td>
                            <?php if (str_contains($outcome['status'], 'review')): ?>
                                <span class="status-review"><?= htmlspecialchars($outcome['status']) ?></span>
                            <?php else: ?>
                                <span class="status-open"><?= htmlspecialchars($outcome['status']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Reading Note</h2>
            <p class="small">This page uses a manually captured snapshot from the Polymarket event page for <strong>US forces enter Iran by...?</strong>. Because market odds can move quickly, treat the displayed numbers as a dated reference point rather than a live quote.</p>
            <p class="small">The 4/30 chart specifically reflects the screenshot values you provided: <strong>Yes 82%</strong>, <strong>No 18%</strong>, <strong>Buy Yes 82c</strong>, and <strong>Buy No 19c</strong>.</p>
            <p class="small">Source snapshot observed on <?= htmlspecialchars($snapshotAt) ?> from <a href="<?= htmlspecialchars($sourceUrl) ?>" target="_blank" rel="noopener">Polymarket</a>.</p>
        </div>
    </section>
</div>

<script>
const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const probabilities = <?= json_encode($probabilities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const volumes = <?= json_encode($volumes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const aprilYes = <?= json_encode($aprilYes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const aprilNo = <?= json_encode($aprilNo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

new Chart(document.getElementById('probabilityChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'Probability %',
                data: probabilities,
                backgroundColor: ['rgba(161, 62, 62, 0.62)', 'rgba(27, 122, 105, 0.72)', 'rgba(141, 91, 23, 0.72)'],
                borderRadius: 8,
                yAxisID: 'y'
            },
            {
                label: 'Volume (USD millions)',
                data: volumes,
                type: 'line',
                borderColor: '#325f9f',
                backgroundColor: 'rgba(50, 95, 159, 0.14)',
                borderWidth: 3,
                tension: 0.22,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                position: 'left',
                title: { display: true, text: 'Probability %' }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                grid: { drawOnChartArea: false },
                title: { display: true, text: 'USD millions' }
            }
        }
    }
});

new Chart(document.getElementById('aprilChart'), {
    type: 'doughnut',
    data: {
        labels: ['Yes', 'No'],
        datasets: [{
            data: [aprilYes, aprilNo],
            backgroundColor: ['rgba(27, 122, 105, 0.78)', 'rgba(161, 62, 62, 0.72)'],
            borderColor: ['#1b7a69', '#a13e3e'],
            borderWidth: 2,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '64%',
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>
</body>
</html>