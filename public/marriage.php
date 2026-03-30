<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\TaiwanLotteryClient;

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'marriage';

function lotteryRangeQuery(): array
{
    $end = new DateTimeImmutable('first day of this month');
    $start = $end->modify('-2 months');

    return [
        'month' => $start->format('Y-m'),
        'endMonth' => $end->format('Y-m'),
        'pageNum' => 1,
        'pageSize' => 200,
    ];
}

function formatBall(int $value): string
{
    return str_pad((string) $value, 2, '0', STR_PAD_LEFT);
}

function formatBallList(array $numbers): string
{
    return implode(' ', array_map(static fn (int $value): string => formatBall($value), $numbers));
}

function compareMainOnly(array $drawMain, array $pickMain): array
{
    $matched = array_values(array_intersect($pickMain, $drawMain));

    return [
        'count' => count($matched),
        'matched' => $matched,
    ];
}

function compareWithSpecial(array $drawMain, int $drawSpecial, array $pickMain, int $pickSpecial): array
{
    $result = compareMainOnly($drawMain, $pickMain);
    $result['specialMatched'] = $drawSpecial === $pickSpecial;

    return $result;
}

function renderMainOnlyResult(array $result): string
{
    $text = (string) $result['count'] . ' hits';
    if ($result['matched']) {
        $text .= ' (' . formatBallList($result['matched']) . ')';
    }

    return $text;
}

function renderSpecialResult(array $result): string
{
    $text = renderMainOnlyResult($result);
    $text .= $result['specialMatched'] ? ' + special hit' : ' + special miss';

    return $text;
}

$superGroups = [
    ['label' => 'Group 1', 'main' => [7, 11, 23, 32, 33, 38], 'special' => 2],
    ['label' => 'Group 2', 'main' => [7, 11, 23, 32, 33, 38], 'special' => 1],
    ['label' => 'Group 3', 'main' => [19, 8, 11, 27, 37, 16], 'special' => 8],
    ['label' => 'Group 4', 'main' => [19, 8, 4, 3, 37, 16], 'special' => 8],
];

$lotto649Groups = [
    ['label' => 'Group 1', 'main' => [19, 8, 11, 27, 37, 16]],
    ['label' => 'Group 2', 'main' => [19, 8, 4, 3, 37, 16]],
];

$daily539Groups = [
    ['label' => 'Group 1', 'main' => [19, 8, 11, 27, 37]],
    ['label' => 'Group 2', 'main' => [19, 8, 4, 3, 37]],
];

$client = new TaiwanLotteryClient($config);
$query = lotteryRangeQuery();
$rangeLabel = $query['month'] . ' to ' . $query['endMonth'];

$sections = [
    'super' => [
        'title' => 'Super Lotto 638',
        'source' => 'https://www.taiwanlottery.com/lotto/result/super_lotto638',
        'groups' => $superGroups,
        'rows' => [],
        'error' => null,
        'has_special' => true,
    ],
    'lotto649' => [
        'title' => 'Lotto 649',
        'source' => 'https://www.taiwanlottery.com/lotto/result/lotto649',
        'groups' => $lotto649Groups,
        'rows' => [],
        'error' => null,
        'has_special' => true,
    ],
    'daily539' => [
        'title' => 'Daily Cash 539',
        'source' => 'https://www.taiwanlottery.com/lotto/result/daily_cash',
        'groups' => $daily539Groups,
        'rows' => [],
        'error' => null,
        'has_special' => false,
    ],
];

try {
    foreach ($client->fetchSuperLotto638($query) as $item) {
        $main = array_map('intval', array_slice($item['drawNumberSize'] ?? [], 0, 6));
        $special = (int) (($item['drawNumberSize'] ?? [])[6] ?? 0);
        $comparisons = [];
        foreach ($superGroups as $group) {
            $comparisons[$group['label']] = renderSpecialResult(compareWithSpecial($main, $special, $group['main'], $group['special']));
        }
        $sections['super']['rows'][] = [
            'period' => (string) $item['period'],
            'date' => substr((string) $item['lotteryDate'], 0, 10),
            'numbers' => formatBallList($main),
            'special' => formatBall($special),
            'comparisons' => $comparisons,
        ];
    }
} catch (Throwable $exception) {
    $sections['super']['error'] = $exception->getMessage();
}

try {
    foreach ($client->fetchLotto649($query) as $item) {
        $main = array_map('intval', array_slice($item['drawNumberSize'] ?? [], 0, 6));
        $special = (int) (($item['drawNumberSize'] ?? [])[6] ?? 0);
        $comparisons = [];
        foreach ($lotto649Groups as $group) {
            $comparisons[$group['label']] = renderMainOnlyResult(compareMainOnly($main, $group['main']));
        }
        $sections['lotto649']['rows'][] = [
            'period' => (string) $item['period'],
            'date' => substr((string) $item['lotteryDate'], 0, 10),
            'numbers' => formatBallList($main),
            'special' => formatBall($special),
            'comparisons' => $comparisons,
        ];
    }
} catch (Throwable $exception) {
    $sections['lotto649']['error'] = $exception->getMessage();
}

try {
    foreach ($client->fetchDaily539($query) as $item) {
        $main = array_map('intval', array_slice($item['drawNumberSize'] ?? [], 0, 5));
        $comparisons = [];
        foreach ($daily539Groups as $group) {
            $comparisons[$group['label']] = renderMainOnlyResult(compareMainOnly($main, $group['main']));
        }
        $sections['daily539']['rows'][] = [
            'period' => (string) $item['period'],
            'date' => substr((string) $item['lotteryDate'], 0, 10),
            'numbers' => formatBallList($main),
            'special' => null,
            'comparisons' => $comparisons,
        ];
    }
} catch (Throwable $exception) {
    $sections['daily539']['error'] = $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>&#26368;&#30606;&#32080;&#23130;&#29702;&#30001;</title>
    <style>
        :root {
            --bg: #f5efe5;
            --panel: rgba(255, 252, 246, 0.94);
            --ink: #1f2a30;
            --muted: #5a666f;
            --accent: #b44343;
            --accent-soft: rgba(180, 67, 67, 0.12);
            --line: rgba(31, 42, 48, 0.1);
            --shadow: 0 18px 50px rgba(31, 42, 48, 0.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Noto Sans TC", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(180, 67, 67, 0.14), transparent 26%),
                linear-gradient(135deg, #efe2c7 0%, #faf6ef 52%, #e4ece8 100%);
            min-height: 100vh;
        }
        .shell { max-width: 1260px; margin: 0 auto; padding: 36px 20px 56px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 252, 246, 0.75); border: 1px solid rgba(31, 42, 48, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #fff; }
        .hero { display: grid; grid-template-columns: 1.3fr 1fr; gap: 22px; margin-bottom: 24px; }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 24px; box-shadow: var(--shadow); padding: 24px; backdrop-filter: blur(10px); }
        .pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 12px 18px; font-size: 0.95rem; font-weight: 700; background: var(--accent-soft); color: var(--accent); }
        h1, h2 { margin: 0 0 12px; }
        h1 { font-size: clamp(2rem, 4vw, 3.6rem); line-height: 1.05; }
        .lead, .small { color: var(--muted); line-height: 1.75; }
        .group-list { display: grid; gap: 12px; margin-top: 18px; }
        .group-item { padding: 14px 16px; border-radius: 18px; background: rgba(31, 42, 48, 0.04); }
        .group-item strong { display: block; margin-bottom: 6px; }
        .section { margin-top: 24px; }
        .section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; margin-bottom: 12px; flex-wrap: wrap; }
        .meta a { color: var(--accent); text-decoration: none; }
        .error { padding: 14px 18px; border-radius: 16px; background: rgba(180, 67, 67, 0.12); color: var(--accent); font-weight: 600; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 980px; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { color: var(--muted); font-size: 0.84rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .balls { font-variant-numeric: tabular-nums; font-weight: 700; }
        .special { color: var(--accent); font-weight: 800; }
        .compare-cell { display: grid; gap: 6px; min-width: 260px; }
        .compare-line { color: var(--muted); line-height: 1.55; }
        .compare-line strong { color: var(--ink); }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        @media (max-width: 980px) {
            .hero { grid-template-columns: 1fr; }
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
            <a class="<?= $currentPage === 'marriage' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/marriage.php') ?>">&#26368;&#30606;&#32080;&#23130;&#29702;&#30001;</a>
            <a class="<?= $currentPage === 'commits' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits &#32113;&#35336;</a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/settings.php') ?>">GitHub Token</a>
        </nav>
    </div>

    <section class="hero">
        <div class="card">
            <div class="pill">Taiwan Lottery Matchboard</div>
            <h1>&#26368;&#30606;&#32080;&#23130;&#29702;&#30001;</h1>
            <p class="lead">This page pulls recent official Taiwan Lottery results, lists each draw, and compares them against the number groups you provided for Super Lotto 638, Lotto 649, and Daily Cash 539.</p>
            <p class="small">Source note: the data comes from Taiwan Lottery official result pages, and the API path is inferred from the official frontend bundles currently used by those pages.</p>
            <p class="small">Query window: <code><?= htmlspecialchars($rangeLabel) ?></code> with page size 200.</p>
        </div>
        <div class="card">
            <h2>Comparison Sets</h2>
            <div class="group-list">
                <div class="group-item"><strong>Super Lotto 638</strong><?= htmlspecialchars('Group 1 07 11 23 32 33 38 special 02 | Group 2 07 11 23 32 33 38 special 01 | Group 3 19 08 11 27 37 16 special 08 | Group 4 19 08 04 03 37 16 special 08') ?></div>
                <div class="group-item"><strong>Lotto 649</strong><?= htmlspecialchars('Group 1 19 08 11 27 37 16 | Group 2 19 08 04 03 37 16') ?></div>
                <div class="group-item"><strong>Daily Cash 539</strong><?= htmlspecialchars('Group 1 19 08 11 27 37 | Group 2 19 08 04 03 37') ?></div>
            </div>
        </div>
    </section>

    <?php foreach ($sections as $section): ?>
        <section class="section card">
            <div class="section-head">
                <div>
                    <h2><?= htmlspecialchars($section['title']) ?></h2>
                    <div class="small meta">Official source: <a href="<?= htmlspecialchars($section['source']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($section['source']) ?></a></div>
                </div>
                <div class="small">Draws loaded: <?= htmlspecialchars((string) count($section['rows'])) ?></div>
            </div>

            <?php if ($section['error']): ?>
                <div class="error"><?= htmlspecialchars($section['error']) ?></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Period</th>
                            <th>Date</th>
                            <th>Winning Numbers</th>
                            <?php if ($section['has_special']): ?>
                                <th>Special</th>
                            <?php endif; ?>
                            <th>Comparisons</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($section['rows'] as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['period']) ?></td>
                                <td><?= htmlspecialchars($row['date']) ?></td>
                                <td class="balls"><?= htmlspecialchars($row['numbers']) ?></td>
                                <?php if ($section['has_special']): ?>
                                    <td class="special"><?= htmlspecialchars((string) $row['special']) ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="compare-cell">
                                        <?php foreach ($row['comparisons'] as $label => $comparison): ?>
                                            <div class="compare-line"><strong><?= htmlspecialchars($label) ?>:</strong> <?= htmlspecialchars($comparison) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
</body>
</html>