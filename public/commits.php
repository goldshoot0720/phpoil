<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\CommitStatsCacheRepository;
use OilApp\CommitStatsService;
use OilApp\SettingsRepository;

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'commits';
$settingsRepository = new SettingsRepository(__DIR__ . '/../storage/app_settings.json');
$cacheRepository = new CommitStatsCacheRepository(__DIR__ . '/../storage/commit_stats_cache.json');

if (isset($_GET['stream']) && $_GET['stream'] === 'refresh') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    $emit = static function (string $event, array $payload): void {
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        @ob_flush();
        @flush();
    };

    try {
        $cacheRepository->clear();
        $emit('progress', [
            'stage' => 'cache_cleared',
            'message' => '已清除舊快取，開始重新抓取 GitHub commits 統計。',
        ]);

        $stats = (new CommitStatsService($config, $cacheRepository))->fetchSummary(
            true,
            static function (array $payload) use ($emit): void {
                $emit('progress', $payload);
            }
        );

        $emit('done', $stats);
    } catch (Throwable $exception) {
        $emit('error', ['message' => $exception->getMessage()]);
    }

    exit;
}

$stats = null;
$error = null;
$message = null;
$forceRefresh = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    try {
        if ($action === 'refresh') {
            $cacheRepository->clear();
            $forceRefresh = true;
            $message = '已先清除快取，並重新抓取 GitHub commits 統計。';
        } elseif ($action === 'clear') {
            $settingsRepository->clearGithubToken();
            $cacheRepository->clear();
            $message = 'GITHUB_TOKEN 已清除。';
        } else {
            $token = trim((string) ($_POST['github_token'] ?? ''));
            if ($token === '') {
                throw new RuntimeException('請先貼上 GITHUB_TOKEN。');
            }

            $settingsRepository->saveGithubToken($token);
            $cacheRepository->clear();
            $forceRefresh = true;
            $message = 'GITHUB_TOKEN 已儲存，現在可以重新抓取 Commits 統計。';
        }

        $config = array_replace_recursive($config, $settingsRepository->all());
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

try {
    $stats = (new CommitStatsService($config, $cacheRepository))->fetchSummary($forceRefresh);
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$tokenConfigured = trim((string) ($config['github']['token'] ?? '')) !== '';
$showTokenPanel = $error !== null || !$tokenConfigured;
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
        .notice.ok { background: rgba(26, 127, 100, 0.12); color: #0f5e4a; }
        .notice.error { background: rgba(180, 67, 67, 0.12); color: var(--danger); }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 24px; box-shadow: var(--shadow); padding: 24px; backdrop-filter: blur(10px); }
        .hero { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px; }
        .metric { font-size: clamp(2rem, 4vw, 3.6rem); font-weight: 800; color: var(--accent); line-height: 1; }
        .label { color: var(--muted); margin-top: 10px; }
        .small { font-size: 0.94rem; color: var(--muted); line-height: 1.7; }
        .token-panel { margin-bottom: 24px; border: 1px solid rgba(26, 127, 100, 0.14); }
        .token-row { display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: end; }
        .token-label { display: block; font-weight: 700; margin-bottom: 8px; }
        .token-input {
            width: 100%;
            min-height: 56px;
            resize: vertical;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(31, 42, 48, 0.12);
            font-size: 0.98rem;
            background: #fff;
            color: var(--ink);
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }
        .token-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 999px;
            padding: 12px 18px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }
        .button.primary { background: var(--ink); color: #fff; }
        .button.secondary { background: rgba(31, 42, 48, 0.08); color: var(--ink); }
        .token-status { margin-top: 12px; font-weight: 700; color: var(--accent); }
        .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
        .toolbar-form { margin: 0; }
        .process-list { margin: 0; padding-left: 20px; color: var(--muted); line-height: 1.8; }
        .process-meta { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top: 18px; }
        .process-pill { padding: 12px 14px; border-radius: 16px; background: rgba(31, 42, 48, 0.05); color: var(--ink); font-weight: 700; }
        .live-progress { margin-bottom: 24px; border: 1px solid rgba(26, 127, 100, 0.16); }
        .progress-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 12px; }
        .progress-line { font-size: 1rem; font-weight: 700; color: var(--ink); }
        .progress-sub { color: var(--muted); font-size: 0.94rem; line-height: 1.7; }
        .progress-bar { width: 100%; height: 12px; border-radius: 999px; background: rgba(31, 42, 48, 0.08); overflow: hidden; margin: 12px 0 16px; }
        .progress-bar-fill { width: 0%; height: 100%; background: linear-gradient(90deg, #1a7f64, #49b58f); transition: width 0.25s ease; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid rgba(31, 42, 48, 0.08); vertical-align: top; }
        th { color: var(--muted); font-size: 0.84rem; text-transform: uppercase; letter-spacing: 0.08em; }
        a.commit-link { color: var(--accent); text-decoration: none; }
        @media (max-width: 860px) {
            .topbar { align-items: flex-start; flex-direction: column; }
            .token-row { grid-template-columns: 1fr; }
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

    <?php if ($showTokenPanel): ?>
        <section class="card token-panel">
            <h2 style="margin: 0 0 12px;">貼上 GITHUB_TOKEN</h2>
            <p class="small">如果 GitHub API 顯示 `403`，直接把 token 貼在這裡即可。儲存後這個頁面會自動用 token 重新抓取資料。</p>
            <form method="post">
                <div class="token-row">
                    <div>
                        <label class="token-label" for="github_token">GITHUB_TOKEN</label>
                        <textarea
                            id="github_token"
                            name="github_token"
                            class="token-input"
                            placeholder="貼上 ghp_xxx 或 github_pat_xxx"
                            spellcheck="false"
                            autocapitalize="off"
                            autocomplete="off"
                        ></textarea>
                    </div>
                    <div class="token-actions">
                        <button class="button primary" type="submit" name="action" value="save">儲存 Token</button>
                        <?php if ($tokenConfigured): ?>
                            <button class="button secondary" type="submit" name="action" value="clear">清除 Token</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            <div class="token-status">目前狀態：<?= $tokenConfigured ? '已設定 Token' : '尚未設定 Token' ?></div>
            <p class="small" style="margin-bottom: 0;">Token 會儲存在 <code>storage/app_settings.json</code>，不會放在 <code>public/</code> 裡，也不會被提交到 Git。</p>
        </section>
    <?php endif; ?>

    <section id="liveProgress" class="card live-progress" style="display: none;">
        <div class="progress-head">
            <h2 style="margin: 0;">抓取過程</h2>
            <div id="liveUpdatedAt" class="small">狀態：等待重新抓取</div>
        </div>
        <div id="liveMessage" class="progress-line">按下「清除快取並重新抓取」後，這裡會顯示即時進度。</div>
        <div class="progress-bar">
            <div id="liveProgressBar" class="progress-bar-fill"></div>
        </div>
        <div id="liveSubMessage" class="progress-sub">尚未開始。</div>
    </section>

    <section class="card" style="margin-bottom: 24px;">
        <h1 style="margin: 0 0 12px;">Commits 統計</h1>
        <p class="small">這個頁面會透過 GitHub API 分頁讀取指定 GitHub 帳號底下的全部 repositories，統計全部 repos 的 commits 總和，並列出 commits 數最高的前 10 名。</p>
    </section>

    <?php if ($stats && $stats['ok']): ?>
        <section class="toolbar">
            <div class="small">
                <span id="updatedAtLabel">最後更新時間：<?= htmlspecialchars($stats['updated_at'] ? date('Y-m-d H:i:s', strtotime((string) $stats['updated_at'])) : '--') ?></span>
            </div>
            <button id="refreshButton" class="button primary" type="button">清除快取並重新抓取</button>
        </section>
        <section class="hero">
            <div class="card">
                <div id="repoCountMetric" class="metric"><?= htmlspecialchars((string) $stats['repo_count']) ?></div>
                <div class="label">Repositories</div>
                <div id="usernameLabel" class="small">帳號：<?= htmlspecialchars((string) $stats['username']) ?></div>
            </div>
            <div class="card">
                <div id="totalCommitsMetric" class="metric"><?= htmlspecialchars((string) $stats['total_commits']) ?></div>
                <div class="label">總 Commits</div>
                <div id="countedRepoLabel" class="small">有 commit 的 repos：<?= htmlspecialchars((string) $stats['counted_repo_count']) ?></div>
            </div>
            <div class="card">
                <div id="top10Metric" class="metric"><?= htmlspecialchars((string) $stats['top10_total_commits']) ?></div>
                <div class="label">前10合計</div>
                <div class="small">前 10 名 repositories commits 加總</div>
            </div>
        </section>

        <section class="card" style="margin-bottom: 24px;">
            <h2 style="margin-top: 0;">統計過程</h2>
            <ol class="process-list">
                <li>使用者按下「清除快取並重新抓取」後，系統會先清除舊快取。</li>
                <li>再分頁抓取 `<?= htmlspecialchars((string) $stats['username']) ?>` 底下全部 repositories。</li>
                <li>接著逐一讀取每個 repository 的預設分支 commits 數。</li>
                <li>把全部 repository 的 commits 加總成 `總 Commits`。</li>
                <li>依 commits 由高到低排序後，取前 10 名再加總成 `前10合計`。</li>
            </ol>
            <div class="process-meta">
                <div id="processRepoCount" class="process-pill">本次抓取 repositories：<?= htmlspecialchars((string) $stats['repo_count']) ?></div>
                <div id="processCountedRepos" class="process-pill">納入 commits 統計：<?= htmlspecialchars((string) $stats['counted_repo_count']) ?></div>
                <div id="processSource" class="process-pill">資料來源：<?= htmlspecialchars($stats['from_cache'] ? '快取結果' : '即時抓取') ?></div>
                <div id="processLatestCommit" class="process-pill">最新 commit 日期：<?= htmlspecialchars($stats['latest_commit_at'] ? date('Y-m-d H:i', strtotime((string) $stats['latest_commit_at'])) : '--') ?></div>
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
                <tbody id="topRepositoriesBody">
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
        <section class="card" style="margin-top: 24px;">
            <p id="latestCommitDateFooter" class="small" style="margin: 0;">最新 commit 日期：<?= htmlspecialchars($stats['latest_commit_at'] ? date('Y-m-d H:i', strtotime((string) $stats['latest_commit_at'])) : '--') ?></p>
        </section>
    <?php elseif ($stats): ?>
        <section class="card">
            <p class="small" style="margin: 0;"><?= htmlspecialchars((string) $stats['message']) ?></p>
            <p class="small" style="margin-bottom: 0;">請先確認 <code>config.php</code> 的 <code>github.username</code>，若遇到 GitHub 403，可直接在本頁上方貼上 <code>GITHUB_TOKEN</code>。</p>
        </section>
    <?php endif; ?>
</div>
<script>
    const refreshButton = document.getElementById('refreshButton');
    const liveProgress = document.getElementById('liveProgress');
    const liveUpdatedAt = document.getElementById('liveUpdatedAt');
    const liveMessage = document.getElementById('liveMessage');
    const liveSubMessage = document.getElementById('liveSubMessage');
    const liveProgressBar = document.getElementById('liveProgressBar');

    const repoCountMetric = document.getElementById('repoCountMetric');
    const totalCommitsMetric = document.getElementById('totalCommitsMetric');
    const top10Metric = document.getElementById('top10Metric');
    const usernameLabel = document.getElementById('usernameLabel');
    const countedRepoLabel = document.getElementById('countedRepoLabel');
    const updatedAtLabel = document.getElementById('updatedAtLabel');
    const processRepoCount = document.getElementById('processRepoCount');
    const processCountedRepos = document.getElementById('processCountedRepos');
    const processSource = document.getElementById('processSource');
    const processLatestCommit = document.getElementById('processLatestCommit');
    const latestCommitDateFooter = document.getElementById('latestCommitDateFooter');
    const topRepositoriesBody = document.getElementById('topRepositoriesBody');

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatDateTime(value) {
        if (!value) return '--';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    }

    function formatShortDateTime(value) {
        if (!value) return '--';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    function renderTopRepositories(repositories) {
        if (!topRepositoriesBody) return;
        topRepositoriesBody.innerHTML = repositories.map((repo, index) => {
            const nameCell = repo.html_url
                ? `<a class="commit-link" href="${escapeHtml(repo.html_url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(repo.full_name)}</a>`
                : escapeHtml(repo.full_name);

            return `
                <tr>
                    <td>${index + 1}</td>
                    <td>${nameCell}</td>
                    <td>${escapeHtml(repo.commits)}</td>
                    <td>${escapeHtml(repo.default_branch)}</td>
                    <td>${escapeHtml(formatShortDateTime(repo.latest_commit_at))}</td>
                </tr>
            `;
        }).join('');
    }

    function updateSummary(stats) {
        if (!stats) return;
        if (repoCountMetric) repoCountMetric.textContent = stats.repo_count ?? '--';
        if (totalCommitsMetric) totalCommitsMetric.textContent = stats.total_commits ?? '--';
        if (top10Metric) top10Metric.textContent = stats.top10_total_commits ?? '--';
        if (usernameLabel) usernameLabel.textContent = `帳號：${stats.username ?? '--'}`;
        if (countedRepoLabel) countedRepoLabel.textContent = `有 commit 的 repos：${stats.counted_repo_count ?? '--'}`;
        if (updatedAtLabel) updatedAtLabel.textContent = `最後更新時間：${formatDateTime(stats.updated_at)}`;
        if (processRepoCount) processRepoCount.textContent = `本次抓取 repositories：${stats.repo_count ?? '--'}`;
        if (processCountedRepos) processCountedRepos.textContent = `納入 commits 統計：${stats.counted_repo_count ?? '--'}`;
        if (processSource) processSource.textContent = `資料來源：${stats.from_cache ? '快取結果' : '即時抓取'}`;
        if (processLatestCommit) processLatestCommit.textContent = `最新 commit 日期：${formatShortDateTime(stats.latest_commit_at)}`;
        if (latestCommitDateFooter) latestCommitDateFooter.textContent = `最新 commit 日期：${formatShortDateTime(stats.latest_commit_at)}`;
        if (Array.isArray(stats.top_repositories)) renderTopRepositories(stats.top_repositories);
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            refreshButton.disabled = true;
            liveProgress.style.display = 'block';
            liveUpdatedAt.textContent = '狀態：準備中';
            liveMessage.textContent = '正在清除快取並準備重新抓取...';
            liveSubMessage.textContent = '請稍候，系統會逐一顯示目前進度。';
            liveProgressBar.style.width = '0%';

            const source = new EventSource('<?= htmlspecialchars(($basePath ?: '') . '/commits.php?stream=refresh', ENT_QUOTES) ?>');

            source.addEventListener('progress', (event) => {
                const payload = JSON.parse(event.data);
                liveUpdatedAt.textContent = '狀態：抓取中';
                liveMessage.textContent = payload.message || '正在抓取...';

                if (payload.stage === 'cache_cleared') {
                    liveSubMessage.textContent = '已先清除快取，接著開始重新抓取 GitHub commits 資料。';
                    liveProgressBar.style.width = '2%';
                    return;
                }

                const current = Number(payload.current || 0);
                const total = Number(payload.total || payload.repo_count || 0);
                const percent = total > 0 ? Math.max(3, Math.round((current / total) * 100)) : 0;
                liveProgressBar.style.width = `${percent}%`;
                liveSubMessage.textContent = total > 0
                    ? `目前進度 ${current}/${total}，正在處理 ${payload.repository || 'repository'}，累計 commits ${payload.total_commits || 0}。`
                    : (payload.message || '正在抓取中...');

                if (repoCountMetric && total > 0) repoCountMetric.textContent = `${current}/${total}`;
                if (totalCommitsMetric && payload.total_commits !== undefined) totalCommitsMetric.textContent = payload.total_commits;
                if (top10Metric && payload.top10_total_commits !== undefined) top10Metric.textContent = payload.top10_total_commits;
            });

            source.addEventListener('done', (event) => {
                const payload = JSON.parse(event.data);
                updateSummary(payload);
                liveUpdatedAt.textContent = `狀態：完成`;
                liveMessage.textContent = '已完成快取清除與 GitHub commits 重新抓取。';
                liveSubMessage.textContent = `最後更新時間：${formatDateTime(payload.updated_at)}`;
                liveProgressBar.style.width = '100%';
                refreshButton.disabled = false;
                source.close();
            });

            source.addEventListener('error', (event) => {
                if (event.data) {
                    const payload = JSON.parse(event.data);
                    liveUpdatedAt.textContent = '狀態：失敗';
                    liveMessage.textContent = '重新抓取失敗。';
                    liveSubMessage.textContent = payload.message || '請稍後再試。';
                } else {
                    liveUpdatedAt.textContent = '狀態：中斷';
                    liveMessage.textContent = '抓取流程已中斷。';
                    liveSubMessage.textContent = '請檢查 GitHub Token 或網路狀態後再試一次。';
                }
                refreshButton.disabled = false;
                source.close();
            });
        });
    }
</script>
</body>
</html>
