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
$birthdayEasterEggs = [
    '04-03' => [
        'kicker' => '04.03 Secret Drop',
        'title' => '&#22615;&#21733;&#29983;&#26085;&#24555;&#27138;',
        'copy' => '&#20170;&#24425;539&#38957;&#29518;&#24471;&#20027;&#37586;&#20804;&#65292;&#20170;&#22825;&#25972;&#31449;&#36914;&#20837;&#24950;&#29983;&#26085;&#27169;&#24335;&#12290;&#25171;&#38283;&#32178;&#31449;&#23601;&#31639;&#27809;&#20013;&#29518;&#65292;&#20063;&#35201;&#20808;&#25226;&#31169;&#36275;&#35588;&#24515;&#25343;&#20986;&#20358;&#12290;',
    ],
    '11-27' => [
        'kicker' => '11.27 Chief Mode',
        'title' => '&#37586;&#20804;&#29983;&#26085;&#24555;&#27138;',
        'copy' => '&#39640;&#32771;&#19977;&#32026;&#36039;&#35338;&#34389;&#29702;&#27036;&#39318;&#37586;&#20804;&#65292;&#20170;&#22825;&#32178;&#31449;&#30452;&#25509;&#20999;&#25563;&#25104;&#24950;&#29983;&#26085;&#20896;&#36557;&#27169;&#24335;&#12290;&#38283;&#31449;&#20808;&#21521;&#22795;&#36575;&#27036;&#39318;&#21814;&#32882;&#65292;&#29992;&#26368;&#39640;&#26684;&#30340;&#29575;&#24615;&#33287;&#24515;&#24773;&#36942;&#23436;&#20170;&#22825;&#12290;',
    ],
];
$birthdayEasterEgg = $birthdayEasterEggs[date('m-d')] ?? null;
$fengBroAscii = <<<'ASCII'
###### ######## ##    ##  ######
##     ##       ###   ## ##
###### ######   ## #  ## ##  ###
##     ##       ##  # ## ##   ##
##     ######## ##   ###  ######

########  ########   #######
##     ## ##     ## ##     ##
##     ## ##     ## ##     ##
########  ########  ##     ##
##     ## ##   ##   ##     ##
##     ## ##    ##  ##     ##
########  ##     ##  #######
ASCII;
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

        .birthday-banner {
            position: relative;
            overflow: hidden;
            margin-bottom: 18px;
            padding: 20px 22px;
            border-radius: 24px;
            background:
                radial-gradient(circle at 15% 20%, rgba(255, 214, 102, 0.36), transparent 26%),
                radial-gradient(circle at 84% 22%, rgba(255, 132, 132, 0.26), transparent 24%),
                linear-gradient(135deg, rgba(31, 42, 48, 0.96), rgba(180, 67, 67, 0.92));
            color: #fff8ef;
            box-shadow: 0 22px 54px rgba(121, 45, 45, 0.24);
        }
        .birthday-banner::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 0%, rgba(255,255,255,0.16) 45%, transparent 100%);
            transform: translateX(-100%);
            animation: birthday-shine 4.8s ease-in-out infinite;
        }
        .birthday-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 248, 239, 0.14);
            font-size: 0.9rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .birthday-title {
            position: relative;
            z-index: 1;
            margin: 0 0 6px;
            font-size: clamp(1.8rem, 4vw, 3rem);
            line-height: 1.05;
        }
        .birthday-copy {
            position: relative;
            z-index: 1;
            margin: 0;
            max-width: 44rem;
            color: rgba(255, 248, 239, 0.88);
            line-height: 1.75;
            font-size: 1rem;
        }
        .birthday-confetti {
            pointer-events: none;
            position: absolute;
            inset: 0;
            overflow: hidden;
        }
        .birthday-confetti span {
            position: absolute;
            top: -24px;
            width: 14px;
            height: 24px;
            border-radius: 999px;
            opacity: 0.9;
            animation: confetti-drop linear infinite;
        }
        .birthday-confetti span:nth-child(1) { left: 8%; background: #ffd166; animation-duration: 8s; animation-delay: -1s; }
        .birthday-confetti span:nth-child(2) { left: 18%; background: #7bd389; animation-duration: 6.5s; animation-delay: -3s; }
        .birthday-confetti span:nth-child(3) { left: 28%; background: #ff8c82; animation-duration: 7.2s; animation-delay: -2s; }
        .birthday-confetti span:nth-child(4) { left: 39%; background: #8ad6ff; animation-duration: 8.8s; animation-delay: -4s; }
        .birthday-confetti span:nth-child(5) { left: 52%; background: #ffe29a; animation-duration: 6.8s; animation-delay: -1.5s; }
        .birthday-confetti span:nth-child(6) { left: 63%; background: #ff9ecb; animation-duration: 7.6s; animation-delay: -2.4s; }
        .birthday-confetti span:nth-child(7) { left: 74%; background: #9be7a9; animation-duration: 6.2s; animation-delay: -3.4s; }
        .birthday-confetti span:nth-child(8) { left: 86%; background: #ffd166; animation-duration: 8.3s; animation-delay: -0.8s; }
        @keyframes confetti-drop {
            0% { transform: translate3d(0, -30px, 0) rotate(0deg); }
            100% { transform: translate3d(0, 180px, 0) rotate(320deg); }
        }
        @keyframes birthday-shine {
            0%, 20% { transform: translateX(-100%); }
            55%, 100% { transform: translateX(100%); }
        }
        .layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid rgba(31, 42, 48, 0.08); }
        th { color: var(--muted); font-size: 0.84rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .small { font-size: 0.92rem; color: var(--muted); line-height: 1.7; }
        .ascii-card {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at top right, rgba(26, 127, 100, 0.22), transparent 34%),
                linear-gradient(145deg, rgba(31, 42, 48, 0.98), rgba(46, 67, 74, 0.95));
            color: #f4efe6;
        }
        .ascii-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px),
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 18px 18px;
            opacity: 0.35;
            pointer-events: none;
        }
        .ascii-label,
        .ascii-copy,
        .ascii-art {
            position: relative;
            z-index: 1;
        }
        .ascii-label {
            display: inline-flex;
            margin-bottom: 14px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(244, 239, 230, 0.1);
            color: rgba(244, 239, 230, 0.8);
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }
        .ascii-copy {
            margin: 0 0 18px;
            max-width: 30rem;
            color: rgba(244, 239, 230, 0.78);
            line-height: 1.7;
        }
        .ascii-art {
            margin: 0;
            padding: 18px;
            border-radius: 18px;
            background: rgba(12, 20, 23, 0.42);
            border: 1px solid rgba(244, 239, 230, 0.12);
            color: #f8d48c;
            font-family: 'Cascadia Mono', Consolas, monospace;
            font-size: clamp(0.53rem, 1vw, 0.82rem);
            line-height: 1.1;
            overflow-x: auto;
        }
        .ascii-art span {
            display: inline-block;
            min-width: max-content;
        }
        @media (max-width: 860px) {
            .layout { grid-template-columns: 1fr; }
            .ascii-art {
                font-size: 0.5rem;
                padding: 14px;
            }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Oil Price Monitor</div>
        <nav class="nav">
            <a class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/') ?>">首頁</a>
            <a class="<?= $currentPage === 'debt' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/debt.php') ?>">US Debt</a>
            <a class="<?= $currentPage === 'population' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/population.php') ?>">&#20154;&#21475;</a>
            <a class="<?= $currentPage === 'pizza' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/pizza.php') ?>">&#25259;&#34217;&#30435;&#25511;</a>
            <a class="<?= $currentPage === 'marriage' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/marriage.php') ?>">&#26368;&#30606;&#32080;&#23130;&#29702;&#30001;</a>
            <a class="<?= $currentPage === 'commits' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits 統計</a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/settings.php') ?>">GitHub Token</a>
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


    <?php if ($birthdayEasterEgg): ?>
        <section class="birthday-banner">
            <div class="birthday-confetti" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span>
            </div>
            <div class="birthday-kicker"><?= $birthdayEasterEgg['kicker'] ?></div>
            <h2 class="birthday-title"><?= $birthdayEasterEgg['title'] ?></h2>
            <p class="birthday-copy"><?= $birthdayEasterEgg['copy'] ?></p>
        </section>
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

        <div class="card ascii-card">
            <div class="ascii-label">ASCII SIGNAL</div>
            <h2>feng bro</h2>
            <p class="ascii-copy">A homepage shout-out, rendered loud and clear for feng bro.</p>
            <pre class="ascii-art" aria-label="feng bro ascii art"><span><?= htmlspecialchars($fengBroAscii) ?></span></pre>
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
