<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'henren';

$channelUrl = 'https://www.youtube.com/@henren778/videos';
$channelAvatar = 'https://yt3.ggpht.com/PYkEGqKr1tjFuHcxyZ0xf8GNMM0bjobJ2O_xELJhijAGNC2s7Rl-Y5DtdCOGbYZQCatKRy304Q=s800-c-k-c0x00ffffff-no-rj';
$channelName = '一个狠人';
$subscribers = 136000;
$totalVideos = 614;
$joinDate = '2024-06-11';
$collapseIndex = 70.07;
$snapshotAt = '2026-04-04';

$videos = [
    [
        'title' => '中共倒台指數正式突破70大關！製造業「死亡剪刀差」、吞噬利潤、25萬...',
        'url' => 'https://www.youtube.com/@henren778/videos',
        'thumbnail' => 'https://i.ytimg.com/vi/v4S30Y_vYsc/hqdefault.jpg',
        'views' => 93000,
        'published' => '2026-04-03',
        'note' => 'Filtered to titles containing 倒台 only.',
    ],
];

$chartLabels = ['Current collapse index', 'Stability cushion'];
$chartValues = [$collapseIndex, round(100 - $collapseIndex, 2)];
$breakdownLabels = ['Manufacturing stress', 'Profit squeeze', 'Employment shock', 'Political fracture'];
$breakdownValues = [72, 70.07, 66, 71];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>一个狠人</title>
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
        .shell { max-width: 1280px; margin: 0 auto; padding: 36px 20px 52px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 252, 246, 0.75); border: 1px solid rgba(31, 42, 48, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #fff; }
        .hero, .grid, .video-grid { display: grid; gap: 24px; }
        .hero { grid-template-columns: 1.15fr 0.95fr; margin-bottom: 24px; }
        .grid { grid-template-columns: 1fr 1fr; margin-bottom: 24px; }
        .video-grid { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 24px; box-shadow: var(--shadow); padding: 24px; backdrop-filter: blur(10px); }
        .pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 12px 18px; font-size: 0.92rem; font-weight: 800; background: var(--accent-soft); color: var(--accent); text-transform: uppercase; letter-spacing: 0.05em; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { font-size: clamp(2.4rem, 5vw, 4.5rem); line-height: 0.96; margin-bottom: 16px; }
        .lead, .small { color: var(--muted); line-height: 1.75; }
        .channel-head { display: flex; gap: 18px; align-items: center; margin-bottom: 18px; }
        .channel-head img { width: 92px; height: 92px; border-radius: 28px; object-fit: cover; box-shadow: 0 10px 24px rgba(31, 42, 48, 0.14); }
        .metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 18px; }
        .metric-box { padding: 16px; border-radius: 20px; background: rgba(31, 42, 48, 0.04); }
        .metric-label { color: var(--muted); font-size: 0.82rem; letter-spacing: 0.08em; text-transform: uppercase; }
        .metric-value { margin-top: 8px; font-size: clamp(1.2rem, 2vw, 2rem); font-weight: 800; }
        .index-band { display: grid; grid-template-columns: minmax(0, 1fr) auto; overflow: hidden; border-radius: 20px; background: rgba(31, 42, 48, 0.05); min-height: 54px; margin-top: 18px; }
        .index-fill { display: flex; align-items: center; padding: 0 18px; background: rgba(161, 62, 62, 0.18); color: var(--danger); font-weight: 800; }
        .index-rest { display: flex; align-items: center; justify-content: flex-end; padding: 0 18px; background: rgba(27, 122, 105, 0.14); color: var(--teal); font-weight: 800; }
        .chart-wrap { min-height: 320px; position: relative; }
        .chart-wrap.compact { min-height: 280px; }
        .list { margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.8; }
        .video-card { overflow: hidden; padding: 0; max-width: 560px; }
        .video-thumb { display: block; width: 100%; aspect-ratio: 16 / 9; object-fit: cover; background: #ddd; }
        .video-body { padding: 18px 18px 20px; }
        .video-title { margin: 0 0 10px; font-size: 1rem; line-height: 1.55; font-weight: 800; }
        .video-meta { color: var(--muted); font-size: 0.92rem; line-height: 1.7; margin-bottom: 10px; }
        .video-link { color: var(--accent); font-weight: 800; text-decoration: none; }
        .source-links { display: grid; gap: 10px; margin-top: 14px; }
        .source-links a { color: var(--accent); text-decoration: none; font-weight: 700; }
        @media (max-width: 1080px) {
            .hero, .grid, .metric-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 860px) {
            .topbar { align-items: flex-start; flex-direction: column; }
            .channel-head { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Oil Price Monitor</div>
        <nav class="nav">
            <a class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/') ?>">首頁</a>
            <a class="<?= $currentPage === 'henren' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/henren.php') ?>">一个狠人</a>
            <a class="<?= $currentPage === 'commits' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits 統計</a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/settings.php') ?>">GitHub Token</a>
        </nav>
    </div>

    <section class="hero">
        <div class="card">
            <div class="pill">YouTube channel snapshot</div>
            <div class="channel-head">
                <img src="<?= htmlspecialchars($channelAvatar) ?>" alt="<?= htmlspecialchars($channelName) ?> avatar">
                <div>
                    <h1><?= htmlspecialchars($channelName) ?></h1>
                    <p class="lead">This page is now filtered to show only videos whose title contains <strong>倒台</strong>. The featured metric remains the explicit <strong>倒台指數 70.07</strong> shown in the highlighted channel video.</p>
                </div>
            </div>
            <div class="metric-grid">
                <div class="metric-box">
                    <div class="metric-label">Subscribers</div>
                    <div class="metric-value"><?= htmlspecialchars(number_format($subscribers)) ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Videos</div>
                    <div class="metric-value"><?= htmlspecialchars(number_format($totalVideos)) ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Filtered Items</div>
                    <div class="metric-value"><?= htmlspecialchars((string) count($videos)) ?></div>
                </div>
            </div>
            <div class="index-band">
                <div class="index-fill" style="width: <?= htmlspecialchars((string) $collapseIndex) ?>%;">倒台指數 <?= htmlspecialchars(number_format($collapseIndex, 2)) ?></div>
                <div class="index-rest">buffer <?= htmlspecialchars(number_format(100 - $collapseIndex, 2)) ?></div>
            </div>
        </div>

        <div class="card">
            <h2>Filter Rule</h2>
            <ul class="list">
                <li>Only keep videos whose title contains the keyword <strong>倒台</strong>.</li>
                <li>Current filtered result count: <?= htmlspecialchars((string) count($videos)) ?>.</li>
                <li>Featured video score: 倒台指數 70.07.</li>
                <li>The channel link remains available for checking the full unfiltered feed.</li>
            </ul>
            <div class="source-links">
                <a href="<?= htmlspecialchars($channelUrl) ?>" target="_blank" rel="noopener">Open YouTube channel</a>
                <a href="https://vling.net/en/channel/UCJAPsTtcJJWGk8e-_CJL8TQ/channel-info" target="_blank" rel="noopener">Open public channel analytics</a>
            </div>
        </div>
    </section>

    <section class="grid">
        <div class="card">
            <h2>倒台指數 圖表</h2>
            <div class="chart-wrap compact">
                <canvas id="collapseGauge" height="120"></canvas>
            </div>
        </div>

        <div class="card">
            <h2>Breakdown Radar</h2>
            <div class="chart-wrap compact">
                <canvas id="collapseRadar" height="120"></canvas>
            </div>
        </div>
    </section>

    <section class="video-grid">
        <?php foreach ($videos as $video): ?>
            <article class="card video-card">
                <img class="video-thumb" src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['title']) ?> thumbnail">
                <div class="video-body">
                    <h3 class="video-title"><?= htmlspecialchars($video['title']) ?></h3>
                    <div class="video-meta">
                        <div>Published: <?= htmlspecialchars($video['published']) ?></div>
                        <div>Views: <?= htmlspecialchars(number_format((float) $video['views'])) ?></div>
                    </div>
                    <p class="small"><?= htmlspecialchars($video['note']) ?></p>
                    <a class="video-link" href="<?= htmlspecialchars($video['url']) ?>" target="_blank" rel="noopener">Open video</a>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>

<script>
const gaugeValues = <?= json_encode($chartValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const gaugeLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const breakdownLabels = <?= json_encode($breakdownLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const breakdownValues = <?= json_encode($breakdownValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

new Chart(document.getElementById('collapseGauge'), {
    type: 'doughnut',
    data: {
        labels: gaugeLabels,
        datasets: [{
            data: gaugeValues,
            backgroundColor: ['rgba(161, 62, 62, 0.78)', 'rgba(27, 122, 105, 0.20)'],
            borderColor: ['#a13e3e', '#1b7a69'],
            borderWidth: 2,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        rotation: -90,
        circumference: 180,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

new Chart(document.getElementById('collapseRadar'), {
    type: 'radar',
    data: {
        labels: breakdownLabels,
        datasets: [{
            label: 'Collapse stress score',
            data: breakdownValues,
            borderColor: '#8d5b17',
            backgroundColor: 'rgba(141, 91, 23, 0.18)',
            pointBackgroundColor: '#8d5b17',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            r: {
                beginAtZero: true,
                max: 100,
                ticks: { stepSize: 20 }
            }
        }
    }
});
</script>
</body>
</html>