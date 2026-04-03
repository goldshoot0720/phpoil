<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\PizzintClient;

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'pizza';

$client = new PizzintClient($config, __DIR__ . '/../resources/pizzint_fallback.json');
$data = $client->load();

function pizzaMetric(?int $value): string
{
    if ($value === null) {
        return '--';
    }

    return number_format($value);
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>&#25259;&#34217;&#30435;&#25511;</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #141414;
            --panel: rgba(29, 29, 29, 0.9);
            --ink: #f7f1df;
            --muted: #c0b7a4;
            --accent: #ffb347;
            --accent-2: #ff7a59;
            --good: #53c68c;
            --line: rgba(255, 255, 255, 0.08);
            --shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            font-family: "Segoe UI", "Noto Sans TC", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(255, 179, 71, 0.14), transparent 24%),
                radial-gradient(circle at 85% 12%, rgba(255, 122, 89, 0.14), transparent 22%),
                linear-gradient(180deg, #161616 0%, #0f0f0f 100%);
            min-height: 100vh;
        }
        .shell { max-width: 1260px; margin: 0 auto; padding: 36px 20px 56px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #151515; }
        .notice { margin-bottom: 16px; padding: 14px 18px; border-radius: 16px; font-weight: 600; background: rgba(255, 122, 89, 0.16); color: #ffd8cd; }
        .hero { display: grid; grid-template-columns: 1.25fr 1fr; gap: 22px; margin-bottom: 24px; }
        .card { background: var(--panel); border: 1px solid rgba(255,255,255,0.06); border-radius: 26px; box-shadow: var(--shadow); padding: 26px; backdrop-filter: blur(10px); }
        .hero-card {
            background:
                radial-gradient(circle at top left, rgba(255, 179, 71, 0.16), transparent 28%),
                linear-gradient(145deg, rgba(32, 32, 32, 0.98), rgba(24, 24, 24, 0.92));
        }
        .pill { display: inline-flex; align-items: center; gap: 10px; border-radius: 999px; padding: 10px 16px; font-size: 0.9rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; }
        .pill.hot { background: rgba(255, 179, 71, 0.16); color: var(--accent); }
        .pill.cool { background: rgba(83, 198, 140, 0.14); color: var(--good); }
        h1, h2, h3 { margin: 0 0 12px; }
        h1 { font-size: clamp(2.3rem, 5vw, 4.5rem); line-height: 0.95; letter-spacing: -0.04em; max-width: 9ch; }
        .lead, .small { color: var(--muted); line-height: 1.8; }
        .hero-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 18px; }
        .hero-stat { padding: 16px; border-radius: 20px; background: rgba(255,255,255,0.04); }
        .hero-label { color: var(--muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .hero-value { margin-top: 8px; font-size: clamp(1.35rem, 2.2vw, 2.2rem); font-weight: 800; }
        .layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .chart-wrap { min-height: 360px; }
        .stack { display: grid; gap: 16px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 720px; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { color: var(--muted); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .status { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; }
        .status-dot { width: 10px; height: 10px; border-radius: 999px; display: inline-block; }
        .status-open { color: var(--good); }
        .status-closed { color: #ff8d8d; }
        .note-list { margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.8; }
        .links a { color: var(--accent); text-decoration: none; }
        @media (max-width: 1020px) {
            .hero, .layout { grid-template-columns: 1fr; }
            .hero-grid { grid-template-columns: 1fr; }
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
            <a class="<?= $currentPage === 'pizza' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/pizza.php') ?>">&#25259;&#34217;&#30435;&#25511;</a>
            <a class="<?= $currentPage === 'population' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/population.php') ?>">&#20154;&#21475;</a>
            <a class="<?= $currentPage === 'marriage' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/marriage.php') ?>">&#26368;&#30606;&#32080;&#23130;&#29702;&#30001;</a>
            <a class="<?= $currentPage === 'commits' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits &#32113;&#35336;</a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/settings.php') ?>">GitHub Token</a>
        </nav>
    </div>

    <?php if ($data['warning']): ?>
        <div class="notice"><?= htmlspecialchars($data['warning']) ?></div>
    <?php endif; ?>

    <section class="hero">
        <div class="card hero-card">
            <div class="pill hot">PizzINT Watch</div>
            <h1>&#25259;&#34217;&#24773;&#22577;&#30475;&#26495;</h1>
            <p class="lead">這頁直接解析 <a href="<?= htmlspecialchars($data['source_url']) ?>" target="_blank" rel="noopener" style="color: var(--accent); text-decoration: none;">pizzint.watch</a> 頁面內嵌的店家資料與 24 小時 sparkline，讓你在站內直接看 Pentagon Pizza Index 的 DOUGHCON、整體熱度與各店波形。</p>
            <div class="hero-grid">
                <div class="hero-stat">
                    <div class="hero-label">DOUGHCON</div>
                    <div class="hero-value"><?= htmlspecialchars((string) $data['defcon_level']) ?></div>
                    <div class="small"><?= htmlspecialchars($data['defcon_label']) ?></div>
                </div>
                <div class="hero-stat">
                    <div class="hero-label">Overall Index</div>
                    <div class="hero-value"><?= htmlspecialchars((string) $data['overall_index']) ?></div>
                    <div class="small">PizzINT overall index</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-label">Locations</div>
                    <div class="hero-value"><?= htmlspecialchars((string) $data['locations_monitored']) ?></div>
                    <div class="small">active watchlist</div>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="pill cool">Source Mode: <?= htmlspecialchars(strtoupper($data['mode'])) ?></div>
                <h2 style="margin-top: 14px;">Latest Snapshot</h2>
                <p class="small">Fetched at: <?= htmlspecialchars($data['fetched_at']) ?></p>
                <p class="small">Open places in DEFCON calc: <?= htmlspecialchars((string) $data['open_places']) ?></p>
                <p class="small">Active spikes: <?= htmlspecialchars((string) $data['active_spike_count']) ?></p>
            </div>

            <div class="card links">
                <h2>&#36039;&#26009;&#20358;&#28304;</h2>
                <p class="small"><a href="<?= htmlspecialchars($data['source_url']) ?>" target="_blank" rel="noopener">PizzINT main page</a></p>
                <ul class="note-list">
                    <?php foreach ($data['notes'] as $note): ?>
                        <li><?= htmlspecialchars($note) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>

    <section class="layout">
        <div class="card">
            <h2>&#26368;&#36817; 24 &#23567;&#26178;&#29105;&#24230;&#26354;&#32218;</h2>
            <p class="small">取最近 24 小時 sparkline 峰值最高的 6 家店，直接畫出 PizzINT 內嵌波形。</p>
            <div class="chart-wrap">
                <canvas id="pizzaLineChart" height="130"></canvas>
            </div>
        </div>

        <div class="card">
            <h2>&#26368;&#26032;&#35264;&#28204;</h2>
            <p class="small">長條圖比較各店最後一個觀測值與 24 小時平均值。若店家已關門，最後觀測值可能會回到 0 或 `none`。</p>
            <div class="chart-wrap" style="min-height: 320px;">
                <canvas id="pizzaBarChart" height="180"></canvas>
            </div>
        </div>
    </section>

    <section class="card" style="margin-top: 24px;">
        <h2>&#30435;&#25511;&#24215;&#23478;</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Latest</th>
                    <th>Avg 24h</th>
                    <th>Peak 24h</th>
                    <th>Status</th>
                    <th>Map</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($data['locations'] as $location): ?>
                    <tr>
                        <td><?= htmlspecialchars($location['name']) ?></td>
                        <td><?= htmlspecialchars(pizzaMetric($location['latest_observed'])) ?></td>
                        <td><?= htmlspecialchars(pizzaMetric($location['avg_24h'])) ?></td>
                        <td><?= htmlspecialchars(pizzaMetric($location['peak_24h'])) ?></td>
                        <td>
                            <?php if ($location['is_closed_now']): ?>
                                <span class="status status-closed"><span class="status-dot" style="background:#ff8d8d;"></span>Closed now</span>
                            <?php else: ?>
                                <span class="status status-open"><span class="status-dot" style="background:#53c68c;"></span>Open now</span>
                            <?php endif; ?>
                        </td>
                        <td><a href="<?= htmlspecialchars($location['address']) ?>" target="_blank" rel="noopener" style="color: var(--accent); text-decoration: none;">Google Maps</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
    const lineLabels = <?= json_encode($data['line_chart']['labels'], JSON_UNESCAPED_UNICODE) ?>;
    const lineDatasets = <?= json_encode($data['line_chart']['datasets'], JSON_UNESCAPED_UNICODE) ?>;
    const barLabels = <?= json_encode($data['bar_chart']['labels'], JSON_UNESCAPED_UNICODE) ?>;
    const barLatest = <?= json_encode($data['bar_chart']['latest'], JSON_UNESCAPED_UNICODE) ?>;
    const barBaseline = <?= json_encode($data['bar_chart']['baseline'], JSON_UNESCAPED_UNICODE) ?>;

    new Chart(document.getElementById('pizzaLineChart'), {
        type: 'line',
        data: {
            labels: lineLabels,
            datasets: lineDatasets.map((dataset) => ({
                label: dataset.label,
                data: dataset.data,
                borderColor: dataset.color,
                backgroundColor: dataset.color + '33',
                borderWidth: 2.5,
                pointRadius: 0,
                spanGaps: true,
                tension: 0.28,
                fill: false
            }))
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    labels: { color: '#f7f1df' }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#c0b7a4', maxTicksLimit: 8 },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                y: {
                    ticks: { color: '#c0b7a4' },
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    beginAtZero: true
                }
            }
        }
    });

    new Chart(document.getElementById('pizzaBarChart'), {
        type: 'bar',
        data: {
            labels: barLabels,
            datasets: [
                {
                    label: 'Latest observed',
                    data: barLatest,
                    backgroundColor: '#ffb347'
                },
                {
                    label: '24h average',
                    data: barBaseline,
                    backgroundColor: '#53c68c'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#f7f1df' }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#c0b7a4', maxRotation: 0, minRotation: 0 },
                    grid: { display: false }
                },
                y: {
                    ticks: { color: '#c0b7a4' },
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    beginAtZero: true
                }
            }
        }
    });
</script>
</body>
</html>
