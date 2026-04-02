<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\WorldPopulationClient;

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'population';

$client = new WorldPopulationClient($config, __DIR__ . '/../resources/world_population_fallback.json');
$data = $client->load();

$series = $data['series'];
$cards = $data['scenario_cards'];
$anchors = $data['anchors'];
$sourceUrls = $data['source_urls'];
$warning = $data['warning'];

function formatPopulation(?float $value): string
{
    if ($value === null) {
        return '—';
    }

    return number_format($value, 0);
}

function formatBillion(?float $value): string
{
    if ($value === null) {
        return '—';
    }

    return number_format($value / 1000000000, 2) . 'B';
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>&#20154;&#21475;&#27169;&#25836;</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #f7f1e8;
            --panel: rgba(255, 252, 247, 0.92);
            --ink: #1d2a2f;
            --muted: #60707a;
            --line: rgba(29, 42, 47, 0.12);
            --official: #155b70;
            --momentum: #c46d2d;
            --balanced: #4e8f5b;
            --aging: #b44343;
            --glow: rgba(196, 109, 45, 0.14);
            --shadow: 0 18px 50px rgba(31, 42, 48, 0.12);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Noto Sans TC", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(196, 109, 45, 0.18), transparent 28%),
                radial-gradient(circle at 85% 18%, rgba(21, 91, 112, 0.14), transparent 24%),
                linear-gradient(135deg, #efe2c7 0%, #f9f6ef 54%, #e4ece8 100%);
            min-height: 100vh;
        }
        .shell { max-width: 1260px; margin: 0 auto; padding: 36px 20px 56px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 252, 246, 0.75); border: 1px solid rgba(31, 42, 48, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #fff; }
        .notice { margin-bottom: 16px; padding: 14px 18px; border-radius: 16px; font-weight: 600; background: rgba(180, 67, 67, 0.12); color: var(--aging); }
        .hero { display: grid; grid-template-columns: 1.2fr 1fr; gap: 22px; margin-bottom: 24px; }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 28px; box-shadow: var(--shadow); padding: 26px; backdrop-filter: blur(10px); }
        .hero-card {
            background:
                linear-gradient(145deg, rgba(255,255,255,0.74), rgba(255,252,247,0.88)),
                radial-gradient(circle at top left, rgba(196,109,45,0.12), transparent 32%);
        }
        .pill { display: inline-flex; align-items: center; gap: 10px; border-radius: 999px; padding: 11px 18px; font-size: 0.94rem; font-weight: 800; letter-spacing: 0.01em; }
        .pill.official { background: rgba(21, 91, 112, 0.12); color: var(--official); }
        .pill.live { background: rgba(196, 109, 45, 0.14); color: #8b531e; }
        h1, h2, h3 { margin: 0 0 12px; }
        h1 { font-size: clamp(2.6rem, 5vw, 4.8rem); line-height: 0.95; letter-spacing: -0.03em; max-width: 10ch; }
        .lead { color: var(--muted); line-height: 1.8; font-size: 1.02rem; margin: 0 0 18px; max-width: 62ch; }
        .hero-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 18px; }
        .hero-stat { padding: 16px 18px; border-radius: 22px; background: rgba(29, 42, 47, 0.04); }
        .hero-label { color: var(--muted); font-size: 0.84rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .hero-value { margin-top: 8px; font-size: clamp(1.3rem, 2.1vw, 2rem); font-weight: 800; line-height: 1.1; }
        .hero-sub { margin-top: 6px; color: var(--muted); font-size: 0.92rem; line-height: 1.6; }
        .stack { display: grid; gap: 16px; }
        .micro { color: var(--muted); font-size: 0.94rem; line-height: 1.75; }
        .link-list { display: grid; gap: 10px; margin-top: 16px; }
        .link-list a { color: var(--official); text-decoration: none; font-weight: 700; }
        .board { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .chart-wrap { min-height: 380px; }
        .legend-grid { display: grid; gap: 14px; }
        .scenario-item { padding: 18px; border-radius: 22px; background: rgba(29, 42, 47, 0.04); border: 1px solid rgba(29, 42, 47, 0.06); }
        .scenario-item h3 { font-size: 1.12rem; margin-bottom: 8px; }
        .scenario-item p { margin: 0; color: var(--muted); line-height: 1.7; }
        .scenario-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
        .scenario-stat { padding: 10px 12px; border-radius: 16px; background: rgba(255, 255, 255, 0.72); }
        .scenario-k { color: var(--muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .scenario-v { margin-top: 6px; font-weight: 800; }
        .meta-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; margin-top: 24px; }
        .note-list { margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.8; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid var(--line); }
        th { color: var(--muted); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .tag { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; }
        .dot { width: 11px; height: 11px; border-radius: 999px; display: inline-block; }
        .muted { color: var(--muted); }
        @media (max-width: 1020px) {
            .hero, .board, .meta-grid { grid-template-columns: 1fr; }
            .hero-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 860px) {
            .topbar { align-items: flex-start; flex-direction: column; }
            .scenario-stats { grid-template-columns: 1fr; }
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
            <a class="<?= $currentPage === 'marriage' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/marriage.php') ?>">&#26368;&#30606;&#32080;&#23130;&#29702;&#30001;</a>
            <a class="<?= $currentPage === 'commits' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits &#32113;&#35336;</a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/settings.php') ?>">GitHub Token</a>
        </nav>
    </div>

    <?php if ($warning): ?>
        <div class="notice"><?= htmlspecialchars($warning) ?></div>
    <?php endif; ?>

    <section class="hero">
        <div class="card hero-card">
            <div class="pill live">Worldometer Base</div>
            <h1>&#19990;&#30028;&#20154;&#21475; 1900-2100</h1>
            <p class="lead">這頁以 Worldometer 目前公開的世界人口頁、歷史表、以及 2026 到 2100 的官方中位線為基底，再額外做三條本地模擬情境，方便直接看峰值、轉折與回落速度。</p>
            <div class="hero-grid">
                <div class="hero-stat">
                    <div class="hero-label">1900 Anchor</div>
                    <div class="hero-value"><?= htmlspecialchars(formatBillion((float) $anchors['population_1900'])) ?></div>
                    <div class="hero-sub">Worldometer 錨點 16 億。</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-label">2026 Base</div>
                    <div class="hero-value"><?= htmlspecialchars(formatBillion((float) $anchors['population_2026'])) ?></div>
                    <div class="hero-sub">目前官方中位線起點。</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-label">2100 Medium</div>
                    <div class="hero-value"><?= htmlspecialchars(formatBillion((float) $anchors['population_2100'])) ?></div>
                    <div class="hero-sub">Worldometer / UN 2024 revision 中位情境。</div>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="pill official">Source Mode: <?= htmlspecialchars(strtoupper($data['mode'])) ?></div>
                <h2 style="margin-top: 14px;"><?= htmlspecialchars($data['live_headline']) ?></h2>
                <p class="micro"><?= htmlspecialchars($data['subtitle']) ?></p>
                <div class="link-list">
                    <a href="<?= htmlspecialchars($sourceUrls['main']) ?>" target="_blank" rel="noopener">Worldometer 主頁</a>
                    <a href="<?= htmlspecialchars($sourceUrls['historical']) ?>" target="_blank" rel="noopener">歷史人口表</a>
                    <a href="<?= htmlspecialchars($sourceUrls['projections']) ?>" target="_blank" rel="noopener">2100 投影表</a>
                </div>
            </div>

            <div class="card">
                <h2>&#23448;&#26041;&#20013;&#20301;&#32218;&#23792;&#20540;</h2>
                <div class="hero-value" style="font-size: clamp(2rem, 3.4vw, 3.2rem); color: var(--official);">
                    <?= htmlspecialchars((string) $anchors['official_peak']['year']) ?>
                </div>
                <p class="micro">官方中位線在 <?= htmlspecialchars((string) $anchors['official_peak']['year']) ?> 年附近來到峰值，約 <?= htmlspecialchars(formatBillion((float) $anchors['official_peak']['population'])) ?>，之後開始非常緩慢地下彎。</p>
            </div>
        </div>
    </section>

    <section class="board">
        <div class="card">
            <h2>&#24773;&#22659;&#26354;&#32218;</h2>
            <p class="micro">藍線是 Worldometer 官方中位線；另外三條是以同一條中位線作為母線的本地模擬。</p>
            <div class="chart-wrap">
                <canvas id="populationChart" height="136"></canvas>
            </div>
        </div>

        <div class="card">
            <h2>&#24773;&#22659;&#25688;&#35201;</h2>
            <div class="legend-grid">
                <?php foreach ($cards as $card): ?>
                    <article class="scenario-item">
                        <div class="tag"><span class="dot" style="background: <?= htmlspecialchars($card['accent']) ?>;"></span><?= htmlspecialchars($card['label']) ?></div>
                        <p><?= htmlspecialchars($card['description']) ?></p>
                        <div class="scenario-stats">
                            <div class="scenario-stat">
                                <div class="scenario-k">2050</div>
                                <div class="scenario-v"><?= htmlspecialchars(formatBillion($card['population_2050'] !== null ? (float) $card['population_2050'] : null)) ?></div>
                            </div>
                            <div class="scenario-stat">
                                <div class="scenario-k">2100</div>
                                <div class="scenario-v"><?= htmlspecialchars(formatBillion($card['population_2100'] !== null ? (float) $card['population_2100'] : null)) ?></div>
                            </div>
                            <div class="scenario-stat" style="grid-column: 1 / -1;">
                                <div class="scenario-k">Peak</div>
                                <div class="scenario-v"><?= htmlspecialchars((string) $card['peak']['year']) ?> / <?= htmlspecialchars(formatBillion((float) $card['peak']['population'])) ?></div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="meta-grid">
        <div class="card">
            <h2>&#37325;&#40670;&#24180;&#20221;</h2>
            <table>
                <thead>
                <tr>
                    <th>Year</th>
                    <th>Official</th>
                    <th>Momentum</th>
                    <th>Balanced</th>
                    <th>Aging</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($data['table_rows'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $row['year']) ?></td>
                        <td><?= htmlspecialchars(formatPopulation($row['official'] !== null ? (float) $row['official'] : null)) ?></td>
                        <td><?= htmlspecialchars(formatPopulation($row['momentum'] !== null ? (float) $row['momentum'] : null)) ?></td>
                        <td><?= htmlspecialchars(formatPopulation($row['balanced'] !== null ? (float) $row['balanced'] : null)) ?></td>
                        <td><?= htmlspecialchars(formatPopulation($row['aging'] !== null ? (float) $row['aging'] : null)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>&#26041;&#27861;&#35498;&#26126;</h2>
            <ul class="note-list">
                <?php foreach ($data['notes'] as $note): ?>
                    <li><?= htmlspecialchars($note) ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="micro" style="margin-top: 18px;">這頁的重點不是宣稱哪條一定成真，而是讓你一眼比較「若下降快一點」和「若延續久一點」時，2100 會差到什麼程度。</p>
        </div>
    </section>
</div>

<script>
    const years = <?= json_encode($data['years'], JSON_UNESCAPED_UNICODE) ?>;
    const official = <?= json_encode(array_values($series['official']), JSON_UNESCAPED_UNICODE) ?>;
    const momentum = <?= json_encode(array_values($series['momentum']), JSON_UNESCAPED_UNICODE) ?>;
    const balanced = <?= json_encode(array_values($series['balanced']), JSON_UNESCAPED_UNICODE) ?>;
    const aging = <?= json_encode(array_values($series['aging']), JSON_UNESCAPED_UNICODE) ?>;

    new Chart(document.getElementById('populationChart'), {
        type: 'line',
        data: {
            labels: years,
            datasets: [
                {
                    label: 'Worldometer medium',
                    data: official,
                    borderColor: '#155b70',
                    backgroundColor: 'rgba(21, 91, 112, 0.10)',
                    borderWidth: 3,
                    pointRadius: 0,
                    tension: 0.24,
                    fill: false
                },
                {
                    label: 'Momentum extension',
                    data: momentum,
                    borderColor: '#c46d2d',
                    borderDash: [10, 7],
                    borderWidth: 2.4,
                    pointRadius: 0,
                    tension: 0.24,
                    fill: false,
                    spanGaps: true
                },
                {
                    label: 'Balanced transition',
                    data: balanced,
                    borderColor: '#4e8f5b',
                    borderDash: [8, 6],
                    borderWidth: 2.4,
                    pointRadius: 0,
                    tension: 0.24,
                    fill: false,
                    spanGaps: true
                },
                {
                    label: 'Rapid aging',
                    data: aging,
                    borderColor: '#b44343',
                    borderDash: [5, 6],
                    borderWidth: 2.4,
                    pointRadius: 0,
                    tension: 0.24,
                    fill: false,
                    spanGaps: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    labels: {
                        boxWidth: 24,
                        usePointStyle: false,
                        color: '#1d2a2f'
                    }
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const value = context.parsed.y;
                            if (value === null || value === undefined) {
                                return context.dataset.label + ': —';
                            }
                            return context.dataset.label + ': ' + Number(value).toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxTicksLimit: 11,
                        color: '#60707a'
                    },
                    grid: {
                        display: false
                    }
                },
                y: {
                    ticks: {
                        color: '#60707a',
                        callback(value) {
                            return (Number(value) / 1000000000).toFixed(1) + 'B';
                        }
                    },
                    grid: {
                        color: 'rgba(29, 42, 47, 0.08)'
                    }
                }
            }
        }
    });
</script>
</body>
</html>
