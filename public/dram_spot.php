<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'dram_spot';

$updatedAt = '2026-04-03 18:10 GMT+8';
$rows = [
    ['item' => 'DDR5 16Gb (2Gx8) 4800/5600', 'daily_high' => 48.00, 'daily_low' => 25.80, 'session_average' => 37.00, 'session_change' => 0.00],
    ['item' => 'DDR5 16Gb (2Gx8) eTT', 'daily_high' => 23.80, 'daily_low' => 20.60, 'session_average' => 21.25, 'session_change' => -0.24],
    ['item' => 'DDR4 16Gb (2Gx8) 3200', 'daily_high' => 90.00, 'daily_low' => 26.20, 'session_average' => 73.091, 'session_change' => -0.31],
    ['item' => 'DDR4 16Gb (2Gx8) eTT', 'daily_high' => 15.00, 'daily_low' => 13.00, 'session_average' => 13.55, 'session_change' => 0.00],
    ['item' => 'DDR4 8Gb (1Gx8) 3200', 'daily_high' => 48.50, 'daily_low' => 12.00, 'session_average' => 33.60, 'session_change' => -0.18],
    ['item' => 'DDR4 8Gb (1Gx8) eTT', 'daily_high' => 7.95, 'daily_low' => 5.60, 'session_average' => 6.73, 'session_change' => -0.53],
    ['item' => 'DDR3 4Gb 512Mx8 1600/1866', 'daily_high' => 9.65, 'daily_low' => 5.50, 'session_average' => 7.70, 'session_change' => 0.98],
];

$labels = array_map(static fn(array $row): string => $row['item'], $rows);
$averages = array_map(static fn(array $row): float => (float) $row['session_average'], $rows);
$latest = $rows[0];
$widestSpread = $rows[0];
foreach ($rows as $row) {
    $spread = $row['daily_high'] - $row['daily_low'];
    $currentSpread = $widestSpread['daily_high'] - $widestSpread['daily_low'];
    if ($spread > $currentSpread) {
        $widestSpread = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DRAM 現貨價格</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #f4efe6;
            --panel: rgba(255, 252, 246, 0.92);
            --ink: #1f2a30;
            --muted: #59656c;
            --accent: #8d5b17;
            --accent-soft: rgba(141, 91, 23, 0.14);
            --accent-2: #2f5b9c;
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
        .shell { max-width: 1240px; margin: 0 auto; padding: 36px 20px 48px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 252, 246, 0.75); border: 1px solid rgba(31, 42, 48, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #fff; }
        .hero, .grid, .meta-grid { display: grid; gap: 24px; }
        .hero { grid-template-columns: 1.15fr 1fr; margin-bottom: 24px; }
        .grid { grid-template-columns: 1.45fr 1fr; }
        .meta-grid { grid-template-columns: 1fr 1fr; margin-top: 24px; }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 24px; box-shadow: var(--shadow); padding: 24px; backdrop-filter: blur(10px); }
        h1, h2, h3 { margin: 0 0 12px; }
        h1 { font-size: clamp(2.4rem, 4.8vw, 4.4rem); line-height: 0.98; max-width: 8ch; }
        .lead, .small { color: var(--muted); line-height: 1.75; }
        .pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 12px 18px; font-size: 0.95rem; font-weight: 700; background: var(--accent-soft); color: var(--accent); }
        .metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 18px; }
        .metric-box { padding: 16px; border-radius: 20px; background: rgba(31, 42, 48, 0.04); }
        .metric-label { color: var(--muted); font-size: 0.82rem; letter-spacing: 0.08em; text-transform: uppercase; }
        .metric-value { margin-top: 8px; font-size: clamp(1.25rem, 2vw, 2rem); font-weight: 800; }
        .flash { margin-top: 16px; padding: 14px 16px; border-radius: 18px; background: rgba(47, 91, 156, 0.10); color: #274c82; font-weight: 700; line-height: 1.6; }
        .chart-wrap { min-height: 390px; }
        .list { margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.8; }
        .source-links { display: grid; gap: 10px; margin-top: 14px; }
        .source-links a { color: var(--accent); text-decoration: none; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid rgba(31, 42, 48, 0.08); }
        th { color: var(--muted); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .tag-up { color: #0f7a57; font-weight: 800; }
        .tag-down { color: #b44343; font-weight: 800; }
        .tag-flat { color: var(--muted); font-weight: 800; }
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
            <a class="<?= $currentPage === 'dram_spot' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/dram_spot.php') ?>">DRAM Spot</a>
            <a class="<?= $currentPage === 'commits' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits &#32113;&#35336;</a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/settings.php') ?>">GitHub Token</a>
        </nav>
    </div>

    <section class="hero">
        <div class="card">
            <div class="pill">TrendForce DRAM Spot</div>
            <h1>DRAM 現貨價格</h1>
            <p class="lead">This page summarizes public DRAM spot quotes visible on TrendForce&apos;s DRAM Price Trends board. It focuses on mainstream DDR3, DDR4, and DDR5 chips and compares the current session average across items.</p>
            <div class="metric-grid">
                <div class="metric-box">
                    <div class="metric-label">Latest Item</div>
                    <div class="metric-value"><?= htmlspecialchars(number_format($latest['session_average'], 2)) ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Last Update</div>
                    <div class="metric-value" style="font-size: 1.15rem;"><?= htmlspecialchars($updatedAt) ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Largest Spread</div>
                    <div class="metric-value"><?= htmlspecialchars(number_format($widestSpread['daily_high'] - $widestSpread['daily_low'], 2)) ?></div>
                </div>
            </div>
            <div class="flash">Public spot data often moves faster than contract data. Among the visible items here, DDR4 16Gb 3200 shows the widest daily range.</div>
        </div>

        <div class="card">
            <h2>Quick Read</h2>
            <ul class="list">
                <li>DDR5 16Gb 4800/5600 session average: 37.00.</li>
                <li>DDR4 16Gb 3200 session average: 73.091, the highest among these public rows.</li>
                <li>DDR3 4Gb 512Mx8 1600/1866 shows a positive session change of +0.98%.</li>
                <li>Several eTT rows stayed flat or slightly softer in this snapshot.</li>
            </ul>
            <div class="source-links">
                <a href="https://www.trendforce.com/price" target="_blank" rel="noopener">TrendForce Price Trends</a>
                <a href="https://www.dramexchange.com/intelligence/price_information.aspx" target="_blank" rel="noopener">DRAMeXchange price information</a>
            </div>
        </div>
    </section>

    <section class="grid">
        <div class="card">
            <h2>Session Average Comparison</h2>
            <div class="chart-wrap">
                <canvas id="dramChart" height="132"></canvas>
            </div>
        </div>

        <div class="card">
            <h2>What This Means</h2>
            <ul class="list">
                <li>Higher session average usually signals tighter supply or stronger bid support in the spot market.</li>
                <li>Large high-low spreads can mean fast-moving negotiations rather than a stable clearing price.</li>
                <li>eTT rows are useful for seeing whether narrower-bin products are lagging or leading mainstream parts.</li>
                <li>This page is a public snapshot page, not a full historical sync yet.</li>
            </ul>
        </div>
    </section>

    <section class="meta-grid">
        <div class="card">
            <h2>Spot Snapshot Table</h2>
            <table>
                <thead>
                <tr>
                    <th>Item</th>
                    <th>High</th>
                    <th>Low</th>
                    <th>Average</th>
                    <th>Change</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['item']) ?></td>
                        <td><?= htmlspecialchars(number_format($row['daily_high'], 2)) ?></td>
                        <td><?= htmlspecialchars(number_format($row['daily_low'], 2)) ?></td>
                        <td><?= htmlspecialchars(number_format($row['session_average'], 3)) ?></td>
                        <td>
                            <?php if ($row['session_change'] > 0): ?>
                                <span class="tag-up">+<?= htmlspecialchars(number_format($row['session_change'], 2)) ?>%</span>
                            <?php elseif ($row['session_change'] < 0): ?>
                                <span class="tag-down"><?= htmlspecialchars(number_format($row['session_change'], 2)) ?>%</span>
                            <?php else: ?>
                                <span class="tag-flat">0.00%</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Source Note</h2>
            <p class="small">Data on this page is based on the public DRAM Spot Price table visible on TrendForce Price Trends. The snapshot used here reflects the board state labeled <strong><?= htmlspecialchars($updatedAt) ?></strong>.</p>
            <p class="small">If you want, the next step can be a real sync job that stores daily DRAM spot snapshots into the database, like the Brent page does for oil.</p>
        </div>
    </section>
</div>

<script>
    const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
    const averages = <?= json_encode($averages, JSON_UNESCAPED_UNICODE) ?>;
    const ctx = document.getElementById('dramChart');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Session Average',
                data: averages,
                backgroundColor: 'rgba(141, 91, 23, 0.65)',
                borderColor: '#8d5b17',
                borderWidth: 1.2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true } },
            scales: {
                x: { ticks: { maxRotation: 45, minRotation: 45 } },
                y: { beginAtZero: true }
            }
        }
    });
</script>
</body>
</html>
