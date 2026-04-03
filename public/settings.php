<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\SettingsRepository;

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'settings';
$settingsRepository = new SettingsRepository(__DIR__ . '/../storage/app_settings.json');
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    try {
        if ($action === 'clear') {
            $settingsRepository->clearGithubToken();
            $message = 'GITHUB_TOKEN 已清除。';
        } else {
            $token = trim((string) ($_POST['github_token'] ?? ''));
            if ($token === '') {
                throw new RuntimeException('請輸入 GITHUB_TOKEN。');
            }

            $settingsRepository->saveGithubToken($token);
            $message = 'GITHUB_TOKEN 已儲存。';
        }

        $config = array_replace_recursive($config, $settingsRepository->all());
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$tokenConfigured = trim((string) (($config['github']['token'] ?? ''))) !== '';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>設定</title>
    <style>
        :root {
            --bg: #f4efe6;
            --panel: rgba(255, 252, 246, 0.92);
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
                radial-gradient(circle at top left, rgba(216, 155, 60, 0.18), transparent 28%),
                linear-gradient(135deg, #efe2c7 0%, #f8f4ec 55%, #dfebe8 100%);
            min-height: 100vh;
        }
        .shell { max-width: 860px; margin: 0 auto; padding: 36px 20px 48px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 252, 246, 0.75); border: 1px solid rgba(31, 42, 48, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #fff; }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 24px; box-shadow: var(--shadow); padding: 24px; backdrop-filter: blur(10px); }
        .notice { margin-bottom: 16px; padding: 14px 18px; border-radius: 16px; font-weight: 600; }
        .notice.ok { background: rgba(26, 127, 100, 0.12); color: #0f5e4a; }
        .notice.error { background: rgba(180, 67, 67, 0.12); color: var(--danger); }
        .small { font-size: 0.94rem; color: var(--muted); line-height: 1.7; }
        label { display: block; font-weight: 700; margin-bottom: 8px; }
        input[type="password"] { width: 100%; padding: 14px 16px; border-radius: 16px; border: 1px solid rgba(31, 42, 48, 0.12); font-size: 1rem; background: #fff; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
        button { border: 0; border-radius: 999px; padding: 12px 18px; font-size: 0.95rem; font-weight: 700; cursor: pointer; }
        .primary { background: var(--ink); color: #fff; }
        .secondary { background: rgba(180, 67, 67, 0.12); color: var(--danger); }
        .status { margin-top: 12px; font-weight: 700; color: var(--accent); }
        @media (max-width: 860px) { .topbar { align-items: flex-start; flex-direction: column; } }
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

    <section class="card">
        <h1 style="margin: 0 0 12px;">GitHub 設定</h1>
        <p class="small">在這裡儲存 `GITHUB_TOKEN`，Commits 統計頁會自動使用這個 token 呼叫 GitHub API，避免公開請求遇到 403 或 rate limit。</p>
        <p class="small">Token 會儲存在 <code>storage/app_settings.json</code>，不會放在 <code>public/</code> 目錄，也已加入 Git ignore。</p>

        <form method="post">
            <label for="github_token">GITHUB_TOKEN</label>
            <input id="github_token" name="github_token" type="password" value="" placeholder="ghp_xxx 或 github_pat_xxx" autocomplete="off">
            <div class="actions">
                <button class="primary" type="submit" name="action" value="save">儲存 Token</button>
                <button class="secondary" type="submit" name="action" value="clear">清除 Token</button>
            </div>
        </form>

        <div class="status">目前狀態：<?= $tokenConfigured ? '已設定 Token' : '尚未設定 Token' ?></div>
    </section>
</div>
</body>
</html>
