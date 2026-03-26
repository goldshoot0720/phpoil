<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\Database;
use OilApp\USDebtRepository;
use OilApp\USDebtScraper;
use OilApp\USDebtService;

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'debt';

$connection = Database::connect($config);
$pdo = $connection['pdo'];
$driver = $connection['driver'];
$warning = $connection['warning'];
Database::ensureSchema($pdo, $driver);

$repository = new USDebtRepository($pdo, $driver);
$message = $_GET['message'] ?? null;
$error = $_GET['error'] ?? null;
$latest = $repository->latest();
$shouldRefresh = $latest === null || (($latest['snapshot_date'] ?? '') !== date('Y-m-d'));

if ($shouldRefresh) {
    try {
        $service = new USDebtService(new USDebtScraper($config), $repository);
        $record = $service->fetchAndStore();
        $message = sprintf('US National Debt updated: %s', number_format((float) $record['debt_amount'], 2));
    } catch (Throwable $exception) {
        $error = 'US Debt fetch failed: ' . $exception->getMessage();
    }
}

$rows = $repository->all();
$latest = $repository->latest();
$recordCount = count($rows);
$previous = $recordCount > 1 ? $rows[$recordCount - 2] : null;
$delta = $latest && $previous ? (float) $latest['debt_amount'] - (float) $previous['debt_amount'] : null;
$deltaLabel = $delta === null ? 'NO PRIOR SNAPSHOT' : sprintf('%+.2f VS PREV DAY', $delta);
$labels = array_map(static fn (array $row): string => $row['snapshot_date'], $rows);
$debtValues = array_map(static fn (array $row): float => (float) $row['debt_amount'], $rows);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>US Debt</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #f4efe6;
            --panel: rgba(255, 252, 246, 0.92);
            --ink: #1f2a30;
            --muted: #59656c;
            --accent: #b44343;
            --accent-soft: rgba(180, 67, 67, 0.14);
            --shadow: 0 18px 50px rgba(31, 42, 48, 0.12);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Noto Sans TC", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(180, 67, 67, 0.18), transparent 28%),
                linear-gradient(135deg, #efe2c7 0%, #f8f4ec 55%, #ebe1dc 100%);
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
        .lead, .small { color: var(--muted); line-height: 1.7; }
        .metric { font-size: clamp(2rem, 4.8vw, 4.2rem); font-weight: 800; color: var(--accent); line-height: 1.05; word-break: break-word; }
        .pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 12px 18px; font-size: 0.95rem; font-weight: 700; background: var(--accent-soft); color: var(--accent); }
        .notice { margin-bottom: 16px; padding: 14px 18px; border-radius: 16px; font-weight: 600; }
        .notice.ok { background: rgba(26, 127, 100, 0.12); color: #0f5e4a; }
        .notice.error { background: rgba(180, 67, 67, 0.12); color: var(--accent); }
        .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .delta { display: inline-flex; margin-top: 14px; border-radius: 999px; padding: 10px 14px; font-weight: 700; background: rgba(31, 42, 48, 0.06); }
        .chart-wrap { min-height: 320px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid rgba(31, 42, 48, 0.08); }
        th { color: var(--muted); font-size: 0.84rem; text-transform: uppercase; letter-spacing: 0.08em; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        @media (max-width: 860px) { .grid { grid-template-columns: 1fr; } .topbar { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Oil Price Monitor</div>
        <nav class="nav">
            <a class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/') ?>">??</a>
            <a class="<?= $currentPage === 'debt' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/debt.php') ?>">US Debt</a>
            <a class="<?= $currentPage === 'commits' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits ??</a>
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
        <div class="notice error"><?= htmlspecialchars($warning) ?></div>
    <?php endif; ?>

    <section class="hero">
        <div class="card">
            <div class="pill">US Debt Clock</div>
            <h1>US National Debt</h1>
            <p class="lead">Source: usdebtclock.org primary top-left debt counter. The page source is parsed on the server, then stored as daily history for charting.</p>
            <p class="small">Primary source target: <code>layer29</code>. This is the primary top-left debt span in the source document.</p>
        </div>

        <div class="card">
            <?php if ($latest): ?>
                <div class="metric"><?= htmlspecialchars(number_format((float) $latest['debt_amount'], 2)) ?></div>
                <div class="small">Snapshot date: <?= htmlspecialchars($latest['snapshot_date']) ?></div>
                <div class="small">Fetched at: <?= htmlspecialchars($latest['fetched_at']) ?></div>
                <div class="small">Rate/sec: <?= htmlspecialchars(number_format((float) $latest['debt_rate_per_second'], 6)) ?></div>
                <div class="delta"><?= htmlspecialchars($deltaLabel) ?></div>
            <?php else: ?>
                <div class="metric">--</div>
                <div class="small">No debt snapshot available yet.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid">
        <div class="card">
            <h2>Debt History</h2>
            <div class="chart-wrap">
                <canvas id="debtChart" height="120"></canvas>
            </div>
        </div>

        <div class="card">
            <h2>Source Notes</h2>
            <?php if ($latest): ?>
                <p class="small">Source URL: <code><?= htmlspecialchars($latest['source_url']) ?></code></p>
                <p class="small">Source layer: <code><?= htmlspecialchars($latest['source_layer']) ?></code></p>
                <p class="small">Element id: <code><?= htmlspecialchars($latest['source_element_id']) ?></code></p>
                <p class="small">Snapshots stored: <?= htmlspecialchars((string) $recordCount) ?></p>
            <?php else: ?>
                <p class="small">No source metadata available yet.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="card" style="margin-top: 24px;">
        <h2>Stored Snapshots</h2>
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Debt Amount</th>
                <th>Rate / Second</th>
                <th>Fetched At</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows): ?>
                <?php foreach (array_reverse($rows) as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['snapshot_date']) ?></td>
                        <td><?= htmlspecialchars(number_format((float) $row['debt_amount'], 2)) ?></td>
                        <td><?= htmlspecialchars(number_format((float) $row['debt_rate_per_second'], 6)) ?></td>
                        <td><?= htmlspecialchars($row['fetched_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No snapshots stored.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<script>
    const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
    const values = <?= json_encode($debtValues, JSON_UNESCAPED_UNICODE) ?>;
    const ctx = document.getElementById('debtChart');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'US National Debt',
                data: values,
                borderColor: '#b44343',
                backgroundColor: 'rgba(180, 67, 67, 0.14)',
                fill: true,
                tension: 0.28,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true } },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback(value) {
                            return Number(value).toLocaleString();
                        }
                    }
                }
            }
        }
    });
</script>
</body>
</html>
