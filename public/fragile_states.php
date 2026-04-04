<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'fsi_china';

$rows = [
    ['year' => 2005, 'rank' => 75, 'score' => 72.3],
    ['year' => 2006, 'rank' => 57, 'score' => 82.5],
    ['year' => 2007, 'rank' => 62, 'score' => 81.2],
    ['year' => 2008, 'rank' => 68, 'score' => 80.3],
    ['year' => 2009, 'rank' => 56, 'score' => 84.6],
    ['year' => 2010, 'rank' => 62, 'score' => 83.0],
    ['year' => 2011, 'rank' => 72, 'score' => 80.1],
    ['year' => 2012, 'rank' => 76, 'score' => 78.3],
    ['year' => 2013, 'rank' => 66, 'score' => 80.9],
    ['year' => 2014, 'rank' => 68, 'score' => 79.0],
    ['year' => 2015, 'rank' => 83, 'score' => 76.5],
    ['year' => 2016, 'rank' => 86, 'score' => 74.9],
    ['year' => 2017, 'rank' => 85, 'score' => 74.7],
    ['year' => 2018, 'rank' => 89, 'score' => 72.4],
    ['year' => 2019, 'rank' => 88, 'score' => 71.1],
    ['year' => 2020, 'rank' => 86, 'score' => 69.9],
    ['year' => 2021, 'rank' => 95, 'score' => 68.9],
    ['year' => 2022, 'rank' => 98, 'score' => 66.9],
    ['year' => 2023, 'rank' => 101, 'score' => 65.1],
    ['year' => 2024, 'rank' => 99, 'score' => 64.4],
];

$latest = $rows[count($rows) - 1];
$previous = $rows[count($rows) - 2];
$peak = $rows[0];
$low = $rows[0];
foreach ($rows as $row) {
    if ($row['score'] > $peak['score']) {
        $peak = $row;
    }
    if ($row['score'] < $low['score']) {
        $low = $row;
    }
}

$scoreChange = $latest['score'] - $previous['score'];
$labels = array_map(static fn (array $row): string => (string) $row['year'], $rows);
$scores = array_map(static fn (array $row): float => (float) $row['score'], $rows);
$ranks = array_map(static fn (array $row): int => (int) $row['rank'], $rows);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fragile States Index China</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #f4efe6;
            --panel: rgba(255, 252, 246, 0.92);
            --ink: #1f2a30;
            --muted: #59656c;
            --accent: #8d5b17;
            --accent-soft: rgba(141, 91, 23, 0.14);
            --accent-2: #b44343;
            --shadow: 0 18px 50px rgba(31, 42, 48, 0.12);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Noto Sans TC", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(141, 91, 23, 0.18), transparent 28%),
                linear-gradient(135deg, #efe2c7 0%, #f8f4ec 55%, #e6ece8 100%);
            min-height: 100vh;
        }
        .shell { max-width: 1240px; margin: 0 auto; padding: 36px 20px 48px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 252, 246, 0.75); border: 1px solid rgba(31, 42, 48, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #fff; }
        .hero, .grid, .meta-grid { display: grid; gap: 24px; }
        .hero { grid-template-columns: 1.2fr 1fr; margin-bottom: 24px; }
        .grid { grid-template-columns: 1.5fr 1fr; }
        .meta-grid { grid-template-columns: 1fr 1fr; margin-top: 24px; }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 24px; box-shadow: var(--shadow); padding: 24px; backdrop-filter: blur(10px); }
        h1, h2, h3 { margin: 0 0 12px; }
        h1 { font-size: clamp(2.4rem, 4.8vw, 4.6rem); line-height: 0.96; max-width: 9ch; }
        .lead, .small { color: var(--muted); line-height: 1.75; }
        .pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 12px 18px; font-size: 0.95rem; font-weight: 700; background: var(--accent-soft); color: var(--accent); }
        .metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 18px; }
        .metric-box { padding: 16px; border-radius: 20px; background: rgba(31, 42, 48, 0.04); }
        .metric-label { color: var(--muted); font-size: 0.82rem; letter-spacing: 0.08em; text-transform: uppercase; }
        .metric-value { margin-top: 8px; font-size: clamp(1.3rem, 2.2vw, 2.2rem); font-weight: 800; }
        .signal { margin-top: 16px; padding: 14px 16px; border-radius: 18px; background: rgba(180, 67, 67, 0.10); color: #7d2d2d; font-weight: 700; line-height: 1.6; }
        .chart-wrap { min-height: 380px; }
        .list { margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.8; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid rgba(31, 42, 48, 0.08); }
        th { color: var(--muted); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .rank-chip { display: inline-flex; min-width: 56px; justify-content: center; padding: 8px 12px; border-radius: 999px; background: rgba(31, 42, 48, 0.06); font-weight: 800; }
        .source-links { display: grid; gap: 10px; margin-top: 14px; }
        .source-links a { color: var(--accent); text-decoration: none; font-weight: 700; }
        @media (max-width: 980px) {
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
            <a class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/') ?>">&#39318;&#38913;</a>
            <a class="<?= $currentPage === 'debt' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/debt.php') ?>">US Debt</a>
            <a class="<?= $currentPage === 'population' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/population.php') ?>">&#20154;&#21475;</a>
            <a class="<?= $currentPage === 'pizza' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/pizza.php') ?>">&#25259;&#34217;&#30435;&#25511;</a>
            <a class="<?= $currentPage === 'marriage' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/marriage.php') ?>">&#26368;&#30606;&#32080;&#23130;&#29702;&#30001;</a>
            <a class="<?= $currentPage === 'fsi_china' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/fragile_states.php') ?>">FSI China</a>
            <a class="<?= $currentPage === 'commits' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits &#32113;&#35336;</a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/settings.php') ?>">GitHub Token</a>
        </nav>
    </div>

    <section class="hero">
        <div class="card">
            <div class="pill">Fund for Peace / China</div>
            <h1>Fragile States Index China</h1>
            <p class="lead">This page tracks China&apos;s Fragile States Index trend from 2005 to 2024. Higher scores indicate more fragility, so the long slide lower since the 2009 peak is the main signal to watch.</p>
            <div class="metric-grid">
                <div class="metric-box">
                    <div class="metric-label">2024 Score</div>
                    <div class="metric-value"><?= htmlspecialchars(number_format($latest['score'], 1)) ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">2024 Rank</div>
                    <div class="metric-value">#<?= htmlspecialchars((string) $latest['rank']) ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">YoY Change</div>
                    <div class="metric-value"><?= htmlspecialchars(sprintf('%+.1f', $scoreChange)) ?></div>
                </div>
            </div>
            <div class="signal">2024 score: 64.4. That is lower than 2023&apos;s 65.1, continuing the multi-year downtrend in fragility.</div>
        </div>

        <div class="card">
            <h2>Quick Read</h2>
            <ul class="list">
                <li>Peak fragility in this series was 84.6 in 2009.</li>
                <li>Lowest score in the series is 64.4 in 2024.</li>
                <li>2024 rank is 99th worldwide in the cited dataset.</li>
                <li>Interpretation: lower is more stable, higher is more fragile.</li>
            </ul>
            <div class="source-links">
                <a href="https://countryeconomy.com/government/fragile-states-index/china" target="_blank" rel="noopener">countryeconomy.com China series</a>
                <a href="https://statbase.org/data/chn-fragile-state-index/" target="_blank" rel="noopener">Statbase China overview</a>
                <a href="https://www.fundforpeace.org/our-work/country-data/" target="_blank" rel="noopener">Fund for Peace country data</a>
            </div>
        </div>
    </section>

    <section class="grid">
        <div class="card">
            <h2>Historical Score</h2>
            <div class="chart-wrap">
                <canvas id="fsiChart" height="130"></canvas>
            </div>
        </div>

        <div class="card">
            <h2>Benchmarks</h2>
            <div class="metric-box">
                <div class="metric-label">Peak Year</div>
                <div class="metric-value"><?= htmlspecialchars((string) $peak['year']) ?></div>
                <div class="small">Score <?= htmlspecialchars(number_format($peak['score'], 1)) ?></div>
            </div>
            <div class="metric-box" style="margin-top: 14px;">
                <div class="metric-label">Lowest Year</div>
                <div class="metric-value"><?= htmlspecialchars((string) $low['year']) ?></div>
                <div class="small">Score <?= htmlspecialchars(number_format($low['score'], 1)) ?></div>
            </div>
            <div class="metric-box" style="margin-top: 14px;">
                <div class="metric-label">Range</div>
                <div class="metric-value"><?= htmlspecialchars(number_format($peak['score'] - $low['score'], 1)) ?></div>
                <div class="small">Points from peak to low</div>
            </div>
        </div>
    </section>

    <section class="meta-grid">
        <div class="card">
            <h2>Yearly Table</h2>
            <table>
                <thead>
                <tr>
                    <th>Year</th>
                    <th>Rank</th>
                    <th>Score</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (array_reverse($rows) as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $row['year']) ?></td>
                        <td><span class="rank-chip">#<?= htmlspecialchars((string) $row['rank']) ?></span></td>
                        <td><?= htmlspecialchars(number_format($row['score'], 1)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>How To Read It</h2>
            <ul class="list">
                <li>The Fragile States Index runs from 0 to 120, where higher means more fragility.</li>
                <li>This page focuses on the total score, not the 12 sub-indicators.</li>
                <li>Ranking is relative to other countries in the same year, while score shows the absolute level in the index framework.</li>
                <li>Source pages above attribute the underlying data to the Fund for Peace.</li>
            </ul>
        </div>
    </section>
</div>

<script>
    const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
    const scores = <?= json_encode($scores, JSON_UNESCAPED_UNICODE) ?>;
    const ranks = <?= json_encode($ranks, JSON_UNESCAPED_UNICODE) ?>;
    const ctx = document.getElementById('fsiChart');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'FSI Score',
                data: scores,
                borderColor: '#8d5b17',
                backgroundColor: 'rgba(141, 91, 23, 0.14)',
                fill: true,
                tension: 0.26,
                pointRadius: 3,
                pointHoverRadius: 5,
                yAxisID: 'y'
            }, {
                label: 'Rank',
                data: ranks,
                borderColor: '#b44343',
                backgroundColor: 'rgba(180, 67, 67, 0.12)',
                fill: false,
                tension: 0.18,
                pointRadius: 2,
                pointHoverRadius: 4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: true } },
            scales: {
                y: { beginAtZero: false, title: { display: true, text: 'Score' } },
                y1: { beginAtZero: false, reverse: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Rank' } }
            }
        }
    });
</script>
</body>
</html>
