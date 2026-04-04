<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\Database;
use OilApp\DatedBrentRepository;
use OilApp\DatedBrentScraper;
use OilApp\DatedBrentService;
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
$datedBrentRepository = new DatedBrentRepository($pdo, $driver);

$messages = [];
$errors = [];
if (isset($_GET['message']) && $_GET['message'] !== '') {
    $messages[] = (string) $_GET['message'];
}
if (isset($_GET['error']) && $_GET['error'] !== '') {
    $errors[] = (string) $_GET['error'];
}

$latest = $repository->latest();
$latestBrent = $datedBrentRepository->latest();
$today = date('Y-m-d');
$autoFetchAt = strtotime($today . ' 13:00:00');
$shouldAutoFetchOqd = time() >= $autoFetchAt
    && (!$latest || $latest['price_date'] !== $today);
$shouldSyncBrent = time() >= $autoFetchAt
    && $datedBrentRepository->latestFetchDate() !== $today;

if ($shouldAutoFetchOqd) {
    try {
        $service = new PriceService(
            new PriceScraper($config),
            $repository
        );
        $record = $service->fetchAndStore();
        $messages[] = sprintf('Auto-fetched OQD Marker Price for %s: %s', $record['price_date'], $record['marker_price']);
    } catch (Throwable $exception) {
        $errors[] = 'OQD auto-fetch failed: ' . $exception->getMessage();
    }
}

if ($shouldSyncBrent) {
    try {
        $datedBrentService = new DatedBrentService(
            new DatedBrentScraper($config),
            $datedBrentRepository
        );
        $records = $datedBrentService->syncHistory();
        $latestSynced = end($records) ?: null;
        if ($latestSynced) {
            $messages[] = sprintf('Synced Dated Brent history through %s: %s', $latestSynced['price_date'], $latestSynced['spot_price']);
        }
    } catch (Throwable $exception) {
        $errors[] = 'Dated Brent sync failed: ' . $exception->getMessage();
    }
}

$rows = $repository->all();
$latest = $repository->latest();
$datedBrentRows = $datedBrentRepository->all();
$latestBrent = $datedBrentRepository->latest();

$labels = array_map(static fn (array $row): string => $row['price_date'], $rows);
$prices = array_map(static fn (array $row): float => (float) $row['marker_price'], $rows);
$datedBrentLabels = array_map(static fn (array $row): string => $row['price_date'], $datedBrentRows);
$datedBrentPrices = array_map(static fn (array $row): float => (float) $row['spot_price'], $datedBrentRows);
$currentPage = 'dashboard';
$birthdayEasterEggs = [
    '04-03' => [
        'kicker' => '04.03 Secret Drop',
        'title' => '塗哥生日快樂',
        'copy' => '今天首頁切進生日模式，先把祝福打在最上面，再來看油價與圖表。',
    ],
    '11-27' => [
        'kicker' => '11.27 Chief Mode',
        'title' => '鋒兄生日快樂',
        'copy' => '今天整站進入慶生版本，資料照跑，氣氛也要到位。',
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
            --accent-2: #2f5b9c;
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
        .shell { max-width: 1240px; margin: 0 auto; padding: 36px 20px 48px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 252, 246, 0.75); border: 1px solid rgba(31, 42, 48, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #fff; }
        .hero { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); margin-bottom: 24px; }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 24px; box-shadow: var(--shadow); padding: 24px; backdrop-filter: blur(10px); }
        h1, h2, h3 { margin: 0 0 12px; }
        h1 { font-size: clamp(2rem, 4vw, 3.5rem); letter-spacing: 0.03em; }
        .lead { color: var(--muted); line-height: 1.7; margin: 0; }
        .metric { font-size: clamp(2.2rem, 5vw, 4.5rem); font-weight: 800; color: var(--accent); line-height: 1; }
        .metric.secondary { color: var(--accent-2); }
        .sub { color: var(--muted); margin-top: 10px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
        .pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 12px 18px; font-size: 0.95rem; font-weight: 700; background: rgba(216, 155, 60, 0.12); color: #8b5d11; }
        .notice { margin-bottom: 16px; padding: 14px 18px; border-radius: 16px; font-weight: 600; }
        .notice.ok { background: rgba(26, 127, 100, 0.12); color: #0f5e4a; }
        .notice.error { background: rgba(180, 67, 67, 0.12); color: var(--danger); }
        .notice.subtle { padding: 10px 14px; font-size: 0.88rem; font-weight: 500; opacity: 0.9; }
        .small { font-size: 0.92rem; color: var(--muted); line-height: 1.7; }
        .eyebrow { font-size: 0.82rem; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; color: #8b5d11; margin-bottom: 10px; }

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
        .birthday-kicker { display: inline-flex; margin-bottom: 10px; padding: 8px 14px; border-radius: 999px; background: rgba(255, 248, 239, 0.14); font-size: 0.9rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        .birthday-copy { margin: 0; max-width: 44rem; color: rgba(255, 248, 239, 0.88); line-height: 1.75; }

        .charts-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
        .table-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; margin-top: 24px; }
        .market-flash { margin: 0 0 16px; padding: 14px 16px; border-radius: 18px; background: linear-gradient(135deg, rgba(180, 67, 67, 0.12), rgba(216, 155, 60, 0.14)); border: 1px solid rgba(180, 67, 67, 0.12); color: #7d2d2d; font-weight: 700; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid rgba(31, 42, 48, 0.08); }
        th { color: var(--muted); font-size: 0.84rem; text-transform: uppercase; letter-spacing: 0.08em; }

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
        .ascii-art { position: relative; z-index: 1; }
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
        .ascii-copy { margin: 0 0 18px; max-width: 30rem; color: rgba(244, 239, 230, 0.78); line-height: 1.7; }
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
        .ascii-art span { display: inline-block; min-width: max-content; }

        @media (max-width: 980px) {
            .charts-grid, .table-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 860px) {
            .topbar { align-items: flex-start; flex-direction: column; }
            .ascii-art { font-size: 0.5rem; padding: 14px; }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Oil Price Monitor</div>
        <nav class="nav">
            <a class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/') ?>">首頁</a>
            <a href="<?= htmlspecialchars(($basePath ?: '') . '/debt.php') ?>">US Debt</a>
            <a href="<?= htmlspecialchars(($basePath ?: '') . '/population.php') ?>">人口</a>
            <a href="<?= htmlspecialchars(($basePath ?: '') . '/pizza.php') ?>">披薩監控</a>
            <a href="<?= htmlspecialchars(($basePath ?: '') . '/marriage.php') ?>">最瞎結婚理由</a>
            <a href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits 統計</a>
            <a href="<?= htmlspecialchars(($basePath ?: '') . '/fragile_states.php') ?>">FSI China</a>
            <a href="<?= htmlspecialchars(($basePath ?: '') . '/dram_spot.php') ?>">DRAM Spot</a>
            <a href="<?= htmlspecialchars(($basePath ?: '') . '/settings.php') ?>">GitHub Token</a>
        </nav>
    </div>

    <?php foreach ($messages as $notice): ?>
        <div class="notice ok"><?= htmlspecialchars($notice) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $notice): ?>
        <div class="notice error"><?= htmlspecialchars($notice) ?></div>
    <?php endforeach; ?>
    <?php if ($warning): ?>
        <div class="notice error subtle"><?= htmlspecialchars($warning) ?></div>
    <?php endif; ?>

    <?php if ($birthdayEasterEgg): ?>
        <section class="birthday-banner">
            <div class="birthday-kicker"><?= htmlspecialchars($birthdayEasterEgg['kicker']) ?></div>
            <h2><?= htmlspecialchars($birthdayEasterEgg['title']) ?></h2>
            <p class="birthday-copy"><?= htmlspecialchars($birthdayEasterEgg['copy']) ?></p>
        </section>
    <?php endif; ?>

    <section class="hero">
        <div class="card ascii-card">
            <div class="ascii-label">ASCII SIGNAL</div>
            <h2>feng bro</h2>
            <p class="ascii-copy">A homepage shout-out, rendered loud and clear for feng bro.</p>
            <pre class="ascii-art" aria-label="feng bro ascii art"><span><?= htmlspecialchars($fengBroAscii) ?></span></pre>
        </div>
    
        <div class="card">
            <div class="pill">油價監控系統</div>
            <h1>OQD Daily Marker Price</h1>
            <p class="lead">首頁會在台北時間下午 1:00 之後自動補抓 OQD 與 Dated Brent，讓面板與圖表一起更新。</p>
            <p class="small">目前資料庫：<?= htmlspecialchars(strtoupper($driver)) ?></p>
            <div class="actions">
                <div class="pill">自動抓取時間：13:00 Asia/Taipei</div>
                <div class="pill">Brent 來源：DataHub / EIA</div>
            </div>
        </div>


    </section>

    <section class="charts-grid" style="margin-bottom: 24px;">
        <div class="card">
            <div class="eyebrow">Latest OQD</div>
            <?php if ($latest): ?>
                <div class="metric"><?= htmlspecialchars(number_format((float) $latest['marker_price'], 2)) ?></div>
                <div class="sub">價格日期：<?= htmlspecialchars($latest['price_date']) ?></div>
                <div class="sub">抓取時間：<?= htmlspecialchars($latest['fetched_at']) ?></div>
                <div class="sub"><?= htmlspecialchars($latest['raw_label']) ?></div>
            <?php else: ?>
                <div class="metric">--</div>
                <div class="sub">尚未抓到 OQD 資料。</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="eyebrow">Dated Brent Spot</div>
            <?php if ($latestBrent): ?>
                <div class="metric secondary"><?= htmlspecialchars(number_format((float) $latestBrent['spot_price'], 2)) ?></div>
                <div class="sub">價格日期：<?= htmlspecialchars($latestBrent['price_date']) ?></div>
                <div class="sub">同步時間：<?= htmlspecialchars($latestBrent['fetched_at']) ?></div>
                <div class="sub"><?= htmlspecialchars($latestBrent['raw_label']) ?></div>
            <?php else: ?>
                <div class="metric secondary">--</div>
                <div class="sub">尚未同步 Dated Brent 資料。</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="charts-grid">
        <div class="card">
            <h2>OQD Marker Price</h2>
            <canvas id="priceChart" height="120"></canvas>
        </div>
        <div class="card">
            <h2>Dated Brent 現貨原油</h2>
            <div class="market-flash">&#20379;&#19981;&#25033;&#27714;&#65281;&#24067;&#34349;&#29305;&#21407;&#27833;&#29694;&#36008;&#26366;&#30772;141&#32654;&#20803;&#65292;&#21109;2008&#24180;&#20197;&#20358;&#39640;&#12290;</div>
            <canvas id="datedBrentChart" height="120"></canvas>
        </div>
    </section>

    <section class="card" style="margin-top: 24px;">
        <h2>排程與來源</h2>
        <p class="small">CLI 排程：<code>php cron/fetch_daily.php</code></p>
        <p class="small">網址排程：<code><?= htmlspecialchars(($basePath ?: '') . '/cron.php?key=' . $config['scraper']['cron_key']) ?></code></p>
        <p class="small">Dated Brent CSV：<code><?= htmlspecialchars($config['dated_brent']['source_url'] ?? '') ?></code></p>
        <p class="small">Brent 歷史同步會把最近一整段資料寫進資料庫，所以圖表一上線就有線，不用等每天慢慢累積。</p>
    </section>

    <section class="table-grid">
        <div class="card">
            <h2>OQD 歷史資料</h2>
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
                        <td colspan="4">目前尚未有任何 OQD 資料。</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Dated Brent 歷史資料</h2>
            <table>
                <thead>
                <tr>
                    <th>價格日期</th>
                    <th>現貨價格</th>
                    <th>同步時間</th>
                    <th>來源文字</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($datedBrentRows): ?>
                    <?php foreach (array_reverse($datedBrentRows) as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['price_date']) ?></td>
                            <td><?= htmlspecialchars(number_format((float) $row['spot_price'], 2)) ?></td>
                            <td><?= htmlspecialchars($row['fetched_at']) ?></td>
                            <td><?= htmlspecialchars($row['raw_label']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">目前尚未有任何 Dated Brent 資料。</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
    const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
    const prices = <?= json_encode($prices, JSON_UNESCAPED_UNICODE) ?>;
    const datedBrentLabels = <?= json_encode($datedBrentLabels, JSON_UNESCAPED_UNICODE) ?>;
    const datedBrentPrices = <?= json_encode($datedBrentPrices, JSON_UNESCAPED_UNICODE) ?>;

    const priceCtx = document.getElementById('priceChart');
    const datedBrentCtx = document.getElementById('datedBrentChart');

    new Chart(priceCtx, {
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
                pointRadius: 3,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: false } }
        }
    });

    new Chart(datedBrentCtx, {
        type: 'line',
        data: {
            labels: datedBrentLabels,
            datasets: [{
                label: 'Dated Brent Spot',
                data: datedBrentPrices,
                borderColor: '#2f5b9c',
                backgroundColor: 'rgba(47, 91, 156, 0.16)',
                fill: true,
                tension: 0.24,
                pointRadius: 2,
                pointHoverRadius: 4
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
