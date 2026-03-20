<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\Database;
use OilApp\PriceRepository;
use OilApp\PriceScraper;
use OilApp\PriceService;

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');

$connection = Database::connect($config);
$pdo = $connection['pdo'];
$driver = $connection['driver'];
$warning = $connection['warning'];
Database::ensureSchema($pdo, $driver);

$repository = new PriceRepository($pdo, $driver);
$message = $_GET['message'] ?? null;
$error = $_GET['error'] ?? null;

$latest = $repository->latest();
$today = date('Y-m-d');
$autoFetchAt = strtotime($today . ' 13:00:00');
$shouldAutoFetch = time() >= $autoFetchAt
    && (!$latest || $latest['price_date'] !== $today);

if ($shouldAutoFetch) {
    try {
        $service = new PriceService(
            new PriceScraper($config),
            $repository
        );
        $record = $service->fetchAndStore();
        $message = sprintf(
            '已自動抓取 %s 的 OQD Marker Price：%s',
            $record['price_date'],
            $record['marker_price']
        );
    } catch (Throwable $exception) {
        $error = '自動抓取失敗：' . $exception->getMessage();
    }
}

$rows = $repository->all();
$latest = $repository->latest();

$labels = array_map(static fn (array $row): string => $row['price_date'], $rows);
$prices = array_map(static fn (array $row): float => (float) $row['marker_price'], $rows);
$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['app_name']) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #f4efe6;
            --panel: rgba(255, 252, 246, 0.92);
            --line: #d89b3c;
            --ink: #1f2a30;
            --muted: #59656c;
            --accent: #1a7f64;
            --danger: #b44343;
            --shadow: 0 18px 50px rgba(31, 42, 48, 0.12);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Noto Sans TC", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(216, 155, 60, 0.22), transparent 30%),
                linear-gradient(135deg, #efe2c7 0%, #f8f4ec 55%, #dfebe8 100%);
            min-height: 100vh;
        }
        .shell { max-width: 1120px; margin: 0 auto; padding: 36px 20px 48px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 252, 246, 0.75); border: 1px solid rgba(31, 42, 48, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #fff; }
        .hero { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); margin-bottom: 24px; }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 24px; box-shadow: var(--shadow); padding: 24px; backdrop-filter: blur(10px); }
        h1, h2 { margin: 0 0 12px; }
        h1 { font-size: clamp(2rem, 4vw, 3.5rem); letter-spacing: 0.03em; }
        .lead { color: var(--muted); line-height: 1.7; margin: 0; }
        .metric { font-size: clamp(2.2rem, 5vw, 4.5rem); font-weight: 800; color: var(--accent); line-height: 1; }
        .sub { color: var(--muted); margin-top: 10px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
        .pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 12px 18px; font-size: 0.95rem; font-weight: 700; background: rgba(216, 155, 60, 0.12); color: #8b5d11; }
        .notice { margin-bottom: 16px; padding: 14px 18px; border-radius: 16px; font-weight: 600; }
        .notice.ok { background: rgba(26, 127, 100, 0.12); color: #0f5e4a; }
        .notice.error { background: rgba(180, 67, 67, 0.12); color: var(--danger); }
        .notice.subtle { padding: 10px 14px; font-size: 0.88rem; font-weight: 500; opacity: 0.9; }
        .layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid rgba(31, 42, 48, 0.08); }
        th { color: var(--muted); font-size: 0.84rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .small { font-size: 0.92rem; color: var(--muted); line-height: 1.7; }
        @media (max-width: 860px) { .layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Oil Price Monitor</div>
        <nav class="nav">
            <a class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/') ?>">首頁</a>
            <a class="<?= $currentPage === 'commits' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits 統計</a>
        </nav>
    </div>

    <?php if ($message): ?>
        <div class="notice ok"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($warning): ?>
        <div class="notice error subtle"><?= htmlspecialchars($warning) ?></div>
    <?php endif; ?>

    <section class="hero">
        <div class="card">
            <div class="pill">油價監控系統</div>
            <h1>OQD Daily Marker Price</h1>
            <p class="lead">
                這個儀表板會在台北時間每天下午 1:00 自動從 GME 頁面抓取 OQD Marker Price，資料儲存後頁面會自動更新。
            </p>
            <p class="small">目前資料庫：<?= htmlspecialchars(strtoupper($driver)) ?></p>
            <div class="actions">
                <div class="pill">自動抓取時間：13:00 Asia/Taipei</div>
                <div class="pill">資料來源：Gulf Mercantile Exchange</div>
            </div>
        </div>

        <div class="card">
            <h2>最新報價</h2>
            <?php if ($latest): ?>
                <div class="metric"><?= htmlspecialchars(number_format((float) $latest['marker_price'], 2)) ?></div>
                <div class="sub">價格日期：<?= htmlspecialchars($latest['price_date']) ?></div>
                <div class="sub">抓取時間：<?= htmlspecialchars($latest['fetched_at']) ?></div>
                <div class="sub"><?= htmlspecialchars($latest['raw_label']) ?></div>
            <?php else: ?>
                <div class="metric">--</div>
                <div class="sub">目前尚未有資料，系統會在排程時間到達後自動抓取。</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="layout">
        <div class="card">
            <h2>歷史價格走勢</h2>
            <canvas id="priceChart" height="120"></canvas>
        </div>

        <div class="card">
            <h2>排程方式</h2>
            <p class="small">CLI 排程：<code>php cron/fetch_daily.php</code></p>
            <p class="small">網址排程：<code><?= htmlspecialchars(($basePath ?: '') . '/cron.php?key=' . $config['scraper']['cron_key']) ?></code></p>
            <p class="small">建議排程時間：每天下午 13:00（台北時間）。</p>
            <p class="small">如果下午 1:00 之後打開頁面，但今天資料仍不存在，系統也會自動補抓一次。</p>
        </div>
    </section>

    <section class="card" style="margin-top: 24px;">
        <h2>歷史資料表</h2>
        <table>
            <thead>
            <tr>
                <th>價格日期</th>
                <th>價格</th>
                <th>抓取時間</th>
                <th>來源文字</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows): ?>
                <?php foreach (array_reverse($rows) as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['price_date']) ?></td>
                        <td><?= htmlspecialchars(number_format((float) $row['marker_price'], 2)) ?></td>
                        <td><?= htmlspecialchars($row['fetched_at']) ?></td>
                        <td><?= htmlspecialchars($row['raw_label']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">目前尚未有任何資料。</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<script>
    const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
    const prices = <?= json_encode($prices, JSON_UNESCAPED_UNICODE) ?>;
    const ctx = document.getElementById('priceChart');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'OQD Marker Price',
                data: prices,
                borderColor: '#d89b3c',
                backgroundColor: 'rgba(216, 155, 60, 0.18)',
                fill: true,
                tension: 0.28,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: false } }
        }
    });
</script>
</body>
</html>
