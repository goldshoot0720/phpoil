<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\CommitStatsService;

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'commits';

$stats = null;
$error = null;

try {
    $stats = (new CommitStatsService($config))->fetchSummary();
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commits 統計</title>
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
                radial-gradient(circle at top left, rgba(26, 127, 100, 0.18), transparent 28%),
                linear-gradient(135deg, #efe2c7 0%, #f8f4ec 55%, #dfebe8 100%);
            min-height: 100vh;
        }
        .shell { max-width: 1120px; margin: 0 auto; padding: 36px 20px 48px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 252, 246, 0.75); border: 1px solid rgba(31, 42, 48, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #fff; }
        .notice { margin-bottom: 16px; padding: 14px 18px; border-radius: 16px; font-weight: 600; }
        .notice.error { background: rgba(180, 67, 67, 0.12); color: var(--danger); }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 24px; box-shadow: var(--shadow); padding: 24px; backdrop-filter: blur(10px); }
        .hero { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px; }
        .metric { font-size: clamp(2rem, 4vw, 3.6rem); font-weight: 800; color: var(--accent); line-height: 1; }
        .label { color: var(--muted); margin-top: 10px; }
        .small { font-size: 0.94rem; color: var(--muted); line-height: 1.7; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid rgba(31, 42, 48, 0.08); vertical-align: top; }
        th { color: var(--muted); font-size: 0.84rem; text-transform: uppercase; letter-spacing: 0.08em; }
        a.commit-link { color: var(--accent); text-decoration: none; }
        @media (max-width: 860px) { .topbar { align-items: flex-start; flex-direction: column; } }
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

    <?php if ($error): ?>
        <div class="notice error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="card" style="margin-bottom: 24px;">
        <h1 style="margin: 0 0 12px;">Commits 統計</h1>
        <p class="small">這個頁面會透過 GitHub API 讀取指定 GitHub 帳號底下的 repositories，統計全部 repos 的 commits 總和，並列出 commits 數最高的前 10 名。</p>
    </section>

    <?php if ($stats && $stats['ok']): ?>
        <section class="hero">
            <div class="card">
                <div class="metric"><?= htmlspecialchars((string) $stats['total_commits']) ?></div>
                <div class="label">總和 commits</div>
                <div class="small">帳號：<?= htmlspecialchars((string) $stats['username']) ?></div>
            </div>
            <div class="card">
                <div class="metric"><?= htmlspecialchars((string) $stats['repo_count']) ?></div>
                <div class="label">納入統計 repos 數</div>
                <div class="small">來源：公開 repositories</div>
            </div>
            <div class="card">
                <div class="metric"><?= htmlspecialchars($stats['latest_commit_at'] ? date('Y-m-d', strtotime((string) $stats['latest_commit_at'])) : '--') ?></div>
                <div class="label">最新 commit 日期</div>
                <div class="small">依 GitHub API 即時讀取</div>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">前十大 Commits</h2>
            <table>
                <thead>
                <tr>
                    <th>排名</th>
                    <th>Repository</th>
                    <th>Commits</th>
                    <th>預設分支</th>
                    <th>最新提交</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($stats['top_repositories'] as $index => $repo): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars((string) ($index + 1)) ?>
                        </td>
                        <td>
                            <?php if ($repo['html_url'] !== ''): ?>
                                <a class="commit-link" href="<?= htmlspecialchars($repo['html_url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($repo['full_name']) ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($repo['full_name']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string) $repo['commits']) ?></td>
                        <td><?= htmlspecialchars($repo['default_branch']) ?></td>
                        <td><?= htmlspecialchars($repo['latest_commit_at'] ? date('Y-m-d H:i', strtotime($repo['latest_commit_at'])) : '--') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php elseif ($stats): ?>
        <section class="card">
            <p class="small" style="margin: 0;"><?= htmlspecialchars((string) $stats['message']) ?></p>
            <p class="small" style="margin-bottom: 0;">請在 <code>config.php</code> 的 <code>github.username</code> 填入目標 GitHub 帳號，例如 <code>goldshoot0720</code>。</p>
        </section>
    <?php endif; ?>
</div>
</body>
</html>
