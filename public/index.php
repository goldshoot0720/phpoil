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
$recordCount = count($rows);
$previous = $recordCount > 1 ? $rows[$recordCount - 2] : null;
$delta = $latest && $previous ? (float) $latest['marker_price'] - (float) $previous['marker_price'] : null;
$deltaLabel = $delta === null ? 'NO PRIOR CLOSE' : sprintf('%+.2f VS PREV', $delta);
$deltaClass = $delta === null ? 'neutral' : ($delta >= 0 ? 'up' : 'down');
$labels = array_map(static fn (array $row): string => $row['price_date'], $rows);
$prices = array_map(static fn (array $row): float => (float) $row['marker_price'], $rows);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['app_name']) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');

        :root {
            --bg: #07111a;
            --bg-2: #0b1d2a;
            --panel: rgba(9, 21, 31, 0.78);
            --panel-strong: rgba(10, 24, 35, 0.92);
            --panel-soft: rgba(18, 35, 49, 0.68);
            --border: rgba(120, 171, 201, 0.16);
            --text: #e9f3f8;
            --muted: #94aebe;
            --cyan: #43d3ff;
            --teal: #1fe0b3;
            --gold: #ffbc57;
            --danger: #ff6e7b;
            --grid: rgba(109, 162, 193, 0.08);
            --shadow: 0 30px 90px rgba(0, 0, 0, 0.36);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            font-family: 'IBM Plex Sans', 'Noto Sans TC', sans-serif;
            background:
                radial-gradient(circle at 10% 10%, rgba(67, 211, 255, 0.12), transparent 0 20%),
                radial-gradient(circle at 86% 14%, rgba(31, 224, 179, 0.10), transparent 0 20%),
                linear-gradient(135deg, var(--bg) 0%, var(--bg-2) 55%, #061019 100%);
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(var(--grid) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
            opacity: 0.7;
        }

        .shell {
            position: relative;
            max-width: 1280px;
            margin: 0 auto;
            padding: 22px 18px 48px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .ticker-row,
        .info-row,
        .hero-badges,
        .mini-grid,
        .schedule-list {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .terminal-tag,
        .badge,
        .micro,
        .ticker {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            border: 1px solid var(--border);
            backdrop-filter: blur(16px);
        }

        .terminal-tag,
        .badge,
        .micro {
            background: rgba(10, 23, 33, 0.72);
        }

        .terminal-tag {
            padding: 11px 16px;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            color: var(--cyan);
        }

        .badge {
            padding: 10px 15px;
            font-size: 0.92rem;
            color: #d7edf6;
        }

        .micro {
            padding: 8px 12px;
            font-size: 0.82rem;
            color: var(--muted);
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
        }

        .ticker {
            padding: 10px 14px;
            background: rgba(255,255,255,0.03);
            color: #cce4f0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 0.88rem;
            letter-spacing: 0.06em;
        }

        .notice {
            margin-bottom: 16px;
            padding: 14px 18px;
            border-radius: 18px;
            border: 1px solid var(--border);
            backdrop-filter: blur(16px);
        }

        .notice.ok { background: rgba(31, 224, 179, 0.10); color: #9af2de; }
        .notice.error { background: rgba(255, 110, 123, 0.10); color: #ffc2c8; }
        .notice.subtle { padding: 10px 14px; font-size: 0.86rem; color: #9cb5c3; }

        .hero {
            display: grid;
            grid-template-columns: 1.3fr 0.9fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(20px);
        }

        .hero-main {
            position: relative;
            overflow: hidden;
            padding: 30px;
            min-height: 380px;
            background:
                linear-gradient(180deg, rgba(9, 21, 31, 0.84), rgba(8, 18, 27, 0.94));
        }

        .hero-main::after {
            content: '';
            position: absolute;
            inset: auto -40px -70px auto;
            width: 280px;
            height: 280px;
            background: radial-gradient(circle, rgba(67, 211, 255, 0.16), transparent 70%);
            filter: blur(12px);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(67, 211, 255, 0.08);
            color: var(--cyan);
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            border: 1px solid rgba(67, 211, 255, 0.16);
        }

        h1, h2, h3, p { margin: 0; }

        h1 {
            max-width: 760px;
            margin-bottom: 18px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(3.2rem, 6vw, 5.9rem);
            line-height: 0.92;
            letter-spacing: -0.06em;
        }

        .lead {
            max-width: 720px;
            margin-bottom: 24px;
            font-size: 1.08rem;
            line-height: 1.95;
            color: var(--muted);
        }

        .hero-badges { margin-bottom: 24px; }

        .mini-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .mini-card {
            padding: 18px;
            border-radius: 22px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
        }

        .mini-label {
            display: block;
            margin-bottom: 10px;
            font-size: 0.78rem;
            letter-spacing: 0.10em;
            color: var(--muted);
            text-transform: uppercase;
        }

        .mini-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.18rem;
            font-weight: 700;
            color: #f1fbff;
            line-height: 1.45;
        }

        .hero-side {
            display: grid;
            gap: 18px;
        }

        .metric-card,
        .schedule-card,
        .chart-card,
        .table-card {
            padding: 28px;
        }

        .metric-card {
            min-height: 380px;
            background:
                linear-gradient(180deg, rgba(10, 24, 35, 0.92), rgba(7, 17, 26, 0.96));
        }

        .metric-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .metric-title {
            font-size: 1.12rem;
            font-weight: 700;
        }

        .price {
            margin: 8px 0 16px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(4rem, 7vw, 5.8rem);
            line-height: 0.94;
            letter-spacing: -0.07em;
            color: #f4fbff;
            text-shadow: 0 0 28px rgba(67, 211, 255, 0.18);
        }

        .delta {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid var(--border);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .delta.up { color: #8df1cf; background: rgba(31, 224, 179, 0.08); }
        .delta.down { color: #ffb0b7; background: rgba(255, 110, 123, 0.08); }
        .delta.neutral { color: #b8ced9; background: rgba(255,255,255,0.04); }

        .metric-meta {
            display: grid;
            gap: 10px;
            color: var(--muted);
            line-height: 1.75;
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(300px, 0.8fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-card,
        .schedule-card,
        .table-card {
            background: var(--panel-strong);
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 14px;
            margin-bottom: 18px;
        }

        .section-head h2,
        .schedule-card h3 {
            font-size: 1.32rem;
            font-weight: 700;
        }

        .section-copy {
            max-width: 520px;
            color: var(--muted);
            line-height: 1.8;
        }

        .chart-wrap {
            min-height: 320px;
            padding: 16px;
            border-radius: 24px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
        }

        .schedule-list {
            display: grid;
            gap: 14px;
            margin-top: 16px;
        }

        .schedule-item {
            padding: 16px;
            border-radius: 20px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
        }

        .schedule-item strong {
            display: block;
            margin-bottom: 6px;
            color: #effaff;
        }

        .schedule-item code {
            font-family: 'Space Grotesk', monospace;
            color: var(--gold);
            word-break: break-all;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 22px;
            background: rgba(255,255,255,0.02);
        }

        th, td {
            text-align: left;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
        }

        th {
            color: var(--muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.10em;
            font-family: 'Space Grotesk', sans-serif;
        }

        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: rgba(67, 211, 255, 0.04); }

        .footer-note {
            color: var(--muted);
            font-size: 0.86rem;
        }

        @media (max-width: 1080px) {
            .hero,
            .layout {
                grid-template-columns: 1fr;
            }

            .mini-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .shell { padding: 14px 12px 32px; }
            .hero-main,
            .metric-card,
            .schedule-card,
            .chart-card,
            .table-card { padding: 22px; }
            .topbar,
            .section-head { flex-direction: column; align-items: flex-start; }
            .price { font-size: 3.5rem; }
            .table-card { overflow-x: auto; }
            table { min-width: 680px; }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="ticker-row">
            <span class="terminal-tag">IMPECCABLE FINANCE UI</span>
            <span class="ticker">OQD / DAILY MARKER / AUTO MODE</span>
            <span class="ticker">EXCHANGE GME</span>
        </div>
        <div class="info-row">
            <span class="micro">DB <?= htmlspecialchars(strtoupper($driver)) ?></span>
            <span class="micro">TZ ASIA/TAIPEI</span>
            <span class="micro">DAILY 13:00</span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="notice ok"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($warning): ?>
        <div class="notice subtle"><?= htmlspecialchars($warning) ?></div>
    <?php endif; ?>

    <section class="hero">
        <article class="card hero-main">
            <div class="eyebrow">科技金融儀表板</div>
            <h1>OQD Daily Marker Price</h1>
            <p class="lead">
                以市場終端機語言重新設計的油價監控首頁。系統會在台北時間每天下午 1:00 自動從 Gulf Mercantile Exchange 擷取 OQD Marker Price，並將最新行情、歷史序列與排程狀態集中呈現在同一個決策畫面。
            </p>

            <div class="hero-badges">
                <span class="badge">自動抓取時間 13:00 Asia/Taipei</span>
                <span class="badge">來源 Gulf Mercantile Exchange</span>
                <span class="badge">首頁補抓 + 排程雙模式</span>
            </div>

            <div class="mini-grid">
                <div class="mini-card">
                    <span class="mini-label">Latest Session</span>
                    <div class="mini-value"><?= htmlspecialchars($latest['price_date'] ?? '--') ?></div>
                </div>
                <div class="mini-card">
                    <span class="mini-label">Total Records</span>
                    <div class="mini-value"><?= htmlspecialchars((string) $recordCount) ?></div>
                </div>
                <div class="mini-card">
                    <span class="mini-label">Cron Endpoint</span>
                    <div class="mini-value" style="font-size: 0.98rem; line-height: 1.55;"><?= htmlspecialchars(($basePath ?: '') . '/cron.php') ?></div>
                </div>
            </div>
        </article>

        <div class="hero-side">
            <article class="card metric-card">
                <div class="metric-head">
                    <h2 class="metric-title">最新報價</h2>
                    <span class="micro">LIVE SNAPSHOT</span>
                </div>
                <div class="price"><?= htmlspecialchars($latest ? number_format((float) $latest['marker_price'], 2) : '--') ?></div>
                <div class="delta <?= htmlspecialchars($deltaClass) ?>"><?= htmlspecialchars($deltaLabel) ?></div>
                <div class="metric-meta">
                    <div>價格日期：<?= htmlspecialchars($latest['price_date'] ?? '--') ?></div>
                    <div>抓取時間：<?= htmlspecialchars($latest['fetched_at'] ?? '--') ?></div>
                    <div><?= htmlspecialchars($latest['raw_label'] ?? '目前尚未有資料，系統會在排程時間到達後自動抓取。') ?></div>
                </div>
            </article>
        </div>
    </section>

    <section class="layout">
        <article class="card chart-card">
            <div class="section-head">
                <div>
                    <h2>歷史價格走勢</h2>
                    <div class="section-copy">用偏交易介面的折線圖表現 OQD Marker Price 的時間序列，讓近期變化、節奏與異動更容易判讀。</div>
                </div>
                <span class="badge">RECORDS <?= htmlspecialchars((string) $recordCount) ?></span>
            </div>
            <div class="chart-wrap">
                <canvas id="priceChart" height="120"></canvas>
            </div>
        </article>

        <aside class="card schedule-card">
            <h3>排程方式</h3>
            <div class="schedule-list">
                <div class="schedule-item">
                    <strong>CLI 排程</strong>
                    <code>php cron/fetch_daily.php</code>
                </div>
                <div class="schedule-item">
                    <strong>網址排程</strong>
                    <code><?= htmlspecialchars(($basePath ?: '') . '/cron.php?key=' . $config['scraper']['cron_key']) ?></code>
                </div>
                <div class="schedule-item">
                    <strong>自動補抓</strong>
                    下午 1:00 之後若今日資料尚未存在，使用者開啟首頁時系統會自動補抓一次。
                </div>
            </div>
        </aside>
    </section>

    <section class="card table-card">
        <div class="section-head">
            <div>
                <h2>歷史資料表</h2>
                <div class="section-copy">保留每次抓取的日期、價格與來源字串，便於後續比對、查核與資料追溯。</div>
            </div>
            <div class="footer-note">最後更新：<?= htmlspecialchars($latest['fetched_at'] ?? '--') ?></div>
        </div>
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
                borderColor: '#43d3ff',
                backgroundColor: 'rgba(67, 211, 255, 0.10)',
                pointBackgroundColor: '#ffbc57',
                pointBorderColor: '#07111a',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 7,
                borderWidth: 3,
                fill: true,
                tension: 0.28
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(8, 18, 27, 0.95)',
                    titleColor: '#f4fbff',
                    bodyColor: '#d5e9f3',
                    borderColor: 'rgba(67, 211, 255, 0.2)',
                    borderWidth: 1,
                    titleFont: { family: 'Space Grotesk' },
                    bodyFont: { family: 'Space Grotesk' },
                    padding: 12,
                    cornerRadius: 14,
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(109, 162, 193, 0.06)' },
                    ticks: { color: '#8fa9b8' }
                },
                y: {
                    beginAtZero: false,
                    grid: { color: 'rgba(109, 162, 193, 0.08)' },
                    ticks: { color: '#8fa9b8' }
                }
            }
        }
    });
</script>
</body>
</html>