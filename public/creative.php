<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$currentPage = 'creative';

$topic = trim((string) ($_POST['topic'] ?? '石油、數據與夜晚的終端機'));
$tone = trim((string) ($_POST['tone'] ?? '帶一點科幻感'));
$length = trim((string) ($_POST['length'] ?? '120'));
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maxLength = max(60, min(260, (int) $length));

    $fragments = [
        "夜色像一層靜音的介面，覆在城市與資料流之上。",
        "螢幕上的數字不是冷冰冰的訊號，而是一種正在呼吸的節奏。",
        "我們追蹤的不只是價格，也是在混亂裡尋找秩序的方法。",
        "每一次刷新，都像替未完成的故事補上一句更清楚的旁白。",
        "當終端機亮起，世界被重新翻譯成可理解、可預測、也可想像的線條。",
        "資料表安靜地展開，但背後其實藏著市場、時間與人的情緒波動。",
        "在看似規律的曲線之間，真正迷人的往往是那些突然偏移的瞬間。",
        "創作有時像抓取資料，先等待，再辨識，最後留下值得保存的部分。",
    ];

    $intro = sprintf('主題是「%s」，語氣是「%s」。', $topic, $tone);
    $body = $intro;

    foreach ($fragments as $fragment) {
        if (mb_strlen($body . $fragment) > $maxLength) {
            break;
        }

        $body .= $fragment;
    }

    $result = $body;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>自由創作</title>
    <style>
        :root {
            --bg: #f4efe6;
            --panel: rgba(255, 252, 246, 0.92);
            --ink: #1f2a30;
            --muted: #59656c;
            --accent: #8a4fff;
            --accent-2: #ff8a3d;
            --shadow: 0 18px 50px rgba(31, 42, 48, 0.12);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Noto Sans TC", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(138, 79, 255, 0.16), transparent 28%),
                radial-gradient(circle at bottom right, rgba(255, 138, 61, 0.18), transparent 24%),
                linear-gradient(135deg, #f5eedf 0%, #fcfaf5 50%, #f2ebe4 100%);
            min-height: 100vh;
        }
        .shell { max-width: 1120px; margin: 0 auto; padding: 36px 20px 48px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .brand { font-size: 1.05rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: var(--ink); padding: 10px 14px; border-radius: 999px; background: rgba(255, 252, 246, 0.8); border: 1px solid rgba(31, 42, 48, 0.08); font-weight: 700; }
        .nav a.active { background: var(--ink); color: #fff; }
        .hero { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 24px; margin-bottom: 24px; }
        .card { background: var(--panel); border: 1px solid rgba(31, 42, 48, 0.08); border-radius: 28px; box-shadow: var(--shadow); padding: 28px; backdrop-filter: blur(10px); }
        h1, h2, p { margin: 0; }
        h1 { font-size: clamp(2.4rem, 5vw, 4.2rem); line-height: 1; margin-bottom: 16px; letter-spacing: -0.03em; }
        .lead { color: var(--muted); line-height: 1.9; font-size: 1.02rem; }
        .badge { display: inline-flex; align-items: center; margin-bottom: 14px; border-radius: 999px; padding: 10px 14px; font-size: 0.9rem; font-weight: 700; background: rgba(138, 79, 255, 0.12); color: #6d39d6; }
        .stack { display: grid; gap: 16px; }
        label { display: block; font-weight: 700; margin-bottom: 8px; }
        input, textarea {
            width: 100%;
            border: 1px solid rgba(31, 42, 48, 0.12);
            border-radius: 18px;
            padding: 14px 16px;
            font-size: 1rem;
            background: #fff;
            color: var(--ink);
        }
        textarea { min-height: 220px; resize: vertical; line-height: 1.9; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        button {
            border: 0;
            border-radius: 999px;
            padding: 12px 18px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
        }
        .result {
            white-space: pre-wrap;
            line-height: 2;
            color: var(--ink);
            font-size: 1.02rem;
        }
        .hint { color: var(--muted); font-size: 0.92rem; line-height: 1.7; }
        .art-stage {
            position: relative;
            overflow: hidden;
            min-height: 640px;
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.78), transparent 28%),
                linear-gradient(180deg, rgba(255, 244, 248, 0.96), rgba(255, 247, 233, 0.96));
        }
        .art-copy { margin-bottom: 18px; }
        .art-frame {
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(31, 42, 48, 0.08);
            background: #fff8fb;
            box-shadow: inset 0 0 0 10px rgba(255, 255, 255, 0.35);
        }
        .art-frame svg {
            display: block;
            width: 100%;
            height: auto;
        }
        @media (max-width: 860px) {
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
            <a class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/') ?>">首頁</a>
            <a class="<?= $currentPage === 'commits' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/commits.php') ?>">Commits 統計</a>
            <a class="<?= $currentPage === 'creative' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/creative.php') ?>">自由創作</a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars(($basePath ?: '') . '/settings.php') ?>">GitHub Token</a>
        </nav>
    </div>

    <section class="hero">
        <div class="card">
            <div class="badge">Creative Lab</div>
            <h1>自由創作</h1>
            <p class="lead">
                這個頁面提供一個輕量的創作工作台，你可以輸入主題、語氣與希望的篇幅，快速生成一段短文、文案或氛圍文字。
            </p>
        </div>

        <div class="card">
            <div class="stack">
                <div>
                    <h2 style="margin-bottom: 8px;">適合拿來做什麼</h2>
                    <p class="hint">品牌文案、產品敘述、社群貼文開頭、詩意短句、靈感草稿、深夜風格旁白。</p>
                </div>
                <div>
                    <h2 style="margin-bottom: 8px;">目前風格</h2>
                    <p class="hint">偏向溫暖、帶一點科技感與敘事節奏的中文創作。</p>
                </div>
            </div>
        </div>
    </section>

    <section class="hero">
        <div class="card">
            <form method="post" class="stack">
                <div>
                    <label for="topic">主題</label>
                    <input id="topic" name="topic" type="text" value="<?= htmlspecialchars($topic) ?>" placeholder="例如：油價、終端機、城市、未來感">
                </div>
                <div>
                    <label for="tone">語氣</label>
                    <input id="tone" name="tone" type="text" value="<?= htmlspecialchars($tone) ?>" placeholder="例如：溫柔、科幻、銳利、詩意">
                </div>
                <div>
                    <label for="length">字數上限</label>
                    <input id="length" name="length" type="number" min="60" max="260" value="<?= htmlspecialchars($length) ?>">
                </div>
                <div class="actions">
                    <button type="submit">開始創作</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-bottom: 14px;">創作結果</h2>
            <?php if ($result !== null): ?>
                <div class="result"><?= htmlspecialchars($result) ?></div>
            <?php else: ?>
                <p class="hint">輸入主題後按下「開始創作」，這裡就會出現生成內容。</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="card art-stage">
        <div class="art-copy">
            <h2 style="margin-bottom: 10px;">向量插畫</h2>
            <p class="hint">以下是依照附圖氣質重新創作的原創 SVG：保留粉彩、甜點、雙馬尾與可愛角色氛圍，但不是逐像素複製。</p>
        </div>
        <div class="art-frame">
            <svg viewBox="0 0 900 900" role="img" aria-label="粉彩甜點風雙馬尾少女向量插畫">
                <defs>
                    <linearGradient id="bgGrad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#ffd8e3"/>
                        <stop offset="100%" stop-color="#fff0c4"/>
                    </linearGradient>
                    <linearGradient id="hairGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#565267"/>
                        <stop offset="100%" stop-color="#2c2d3b"/>
                    </linearGradient>
                    <linearGradient id="blueTip" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#71a8ff"/>
                        <stop offset="100%" stop-color="#3c69da"/>
                    </linearGradient>
                    <linearGradient id="dressGrad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#ffd0e4"/>
                        <stop offset="100%" stop-color="#ffb8d7"/>
                    </linearGradient>
                    <linearGradient id="skinGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#fff3ee"/>
                        <stop offset="100%" stop-color="#ffd8c7"/>
                    </linearGradient>
                    <linearGradient id="eyeGrad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#dff5ff"/>
                        <stop offset="100%" stop-color="#6aa3ff"/>
                    </linearGradient>
                    <filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="10" stdDeviation="14" flood-color="#ef7ea2" flood-opacity="0.18"/>
                    </filter>
                </defs>

                <rect width="900" height="900" fill="url(#bgGrad)"/>
                <rect x="50" y="70" width="800" height="730" rx="34" fill="#ff7f86"/>
                <rect x="88" y="108" width="724" height="654" rx="28" fill="#fffaf8"/>

                <g opacity="0.8">
                    <path d="M116 146h120v580H116z" fill="#fff4bf"/>
                    <path d="M390 146h120v580H390z" fill="#fff4bf"/>
                    <path d="M664 146h120v580H664z" fill="#fff4bf"/>
                    <path d="M116 146h668v4H116zM116 260h668v4H116zM116 374h668v4H116zM116 488h668v4H116zM116 602h668v4H116zM116 716h668v4H116z" fill="#ffb7bb"/>
                    <path d="M116 146v578M236 146v578M390 146v578M510 146v578M664 146v578M784 146v578" stroke="#ffb7bb" stroke-width="4"/>
                </g>

                <g fill="#ffffff" opacity="0.95">
                    <path d="M125 112c18 8 28 21 31 38 17-18 37-23 58-13-4 16-15 27-33 35 15 8 22 20 23 38-20-3-34-11-44-25-9 17-25 27-47 31 3-19 10-32 22-40-16-5-25-15-28-31 10-5 17-6 18-6z"/>
                    <path d="M676 112c18 8 28 21 31 38 17-18 37-23 58-13-4 16-15 27-33 35 15 8 22 20 23 38-20-3-34-11-44-25-9 17-25 27-47 31 3-19 10-32 22-40-16-5-25-15-28-31 10-5 17-6 18-6z"/>
                    <path d="M125 653c18 8 28 21 31 38 17-18 37-23 58-13-4 16-15 27-33 35 15 8 22 20 23 38-20-3-34-11-44-25-9 17-25 27-47 31 3-19 10-32 22-40-16-5-25-15-28-31 10-5 17-6 18-6z"/>
                    <path d="M676 653c18 8 28 21 31 38 17-18 37-23 58-13-4 16-15 27-33 35 15 8 22 20 23 38-20-3-34-11-44-25-9 17-25 27-47 31 3-19 10-32 22-40-16-5-25-15-28-31 10-5 17-6 18-6z"/>
                </g>

                <g transform="translate(448 210)" filter="url(#softShadow)">
                    <ellipse cx="0" cy="12" rx="196" ry="80" fill="#ffe698"/>
                    <ellipse cx="0" cy="24" rx="174" ry="62" fill="#fff2b3"/>
                </g>

                <g transform="translate(452 454)">
                    <path d="M-262 54c14-79 61-154 128-194 43-26 91-37 134-36 48 1 104 19 150 50 55 37 99 103 111 180 11 75-9 148-66 198-58 51-140 76-204 78-86 4-176-29-228-105-29-42-38-105-25-171z" fill="url(#skinGrad)"/>

                    <g>
                        <path d="M-234 -46c-35-48-55-106-44-160 7-37 31-80 75-104 29-15 64-13 89 8 18 16 21 41 7 60-17 24-45 50-66 78-17 22-17 44 11 58 29 15 51 41 39 68-13 29-54 24-111-2z" fill="url(#hairGrad)"/>
                        <path d="M233 -47c36-47 58-105 47-159-7-37-31-81-76-104-29-15-64-13-89 8-18 16-21 41-7 60 17 24 45 50 66 78 17 22 17 44-11 58-29 15-51 41-39 68 13 29 54 24 109-9z" fill="url(#hairGrad)"/>
                        <path d="M-204 76c-17 53-49 103-97 128-24 12-53 17-66 3-10-11 5-41 25-63 23-26 52-47 72-75z" fill="url(#blueTip)"/>
                        <path d="M206 76c17 53 49 103 97 128 24 12 53 17 66 3 10-11-5-41-25-63-23-26-52-47-72-75z" fill="url(#blueTip)"/>
                    </g>

                    <path d="M-166 -159c56-91 226-111 323-45 37 25 61 61 69 101 10 56-3 105-25 160-18-16-58-19-69-55-13-41-3-89-28-125-17-24-50-36-78-45-67-20-144-9-192 44z" fill="url(#hairGrad)"/>
                    <path d="M-170 -144c61-72 151-102 242-80 47 11 91 40 114 83 22 42 20 96 8 140-18-20-44-35-75-40-44-6-81-4-118-31-29-22-48-62-89-69-31-5-58 8-82 26z" fill="url(#hairGrad)"/>

                    <path d="M-58 160c-62 2-116 22-145 74-18 32-34 72-40 118h482c-4-57-16-97-42-128-32-39-76-55-137-64-34 42-81 63-118 63z" fill="url(#dressGrad)"/>
                    <path d="M-125 176c17-15 47-27 74-31 22-4 44 2 63 14 19 12 42 17 65 13 21-3 42-15 62-22 32-11 68-10 97 4-8 39-74 59-121 61-64 4-180 4-240-39z" fill="#ffc3df"/>

                    <path d="M-174 182c18 6 30 17 31 36 1 25-17 43-45 46-36 4-67-19-77-56 27-16 58-20 91-26z" fill="#fffefd"/>
                    <path d="M178 182c-18 6-30 17-31 36-1 25 17 43 45 46 36 4 67-19 77-56-27-16-58-20-91-26z" fill="#fffefd"/>

                    <g transform="translate(-33 -12)">
                        <ellipse cx="0" cy="0" rx="124" ry="142" fill="#fff4f1"/>
                        <path d="M-109 -82c36-74 122-108 202-74 44 18 81 65 83 109-20-18-48-33-80-36-33-4-64 3-92-11-25-13-36-40-59-53-16-9-36-7-54 3z" fill="url(#hairGrad)"/>
                    </g>

                    <g transform="translate(-124 -20)">
                        <path d="M0 0c24-12 47-10 68 10" stroke="#3b1f26" stroke-width="8" stroke-linecap="round" fill="none"/>
                        <path d="M6 -2c17-5 34 0 51 10" stroke="#6a424e" stroke-width="3" stroke-linecap="round" fill="none"/>
                    </g>
                    <g transform="translate(68 18)">
                        <ellipse cx="0" cy="0" rx="38" ry="48" fill="url(#eyeGrad)"/>
                        <ellipse cx="-7" cy="-5" rx="23" ry="29" fill="#91c4ff"/>
                        <ellipse cx="-11" cy="-7" rx="15" ry="20" fill="#d9f3ff"/>
                        <circle cx="10" cy="-12" r="8" fill="#ffffff"/>
                        <circle cx="-4" cy="-16" r="5" fill="#ffffff"/>
                        <circle cx="7" cy="11" r="4" fill="#ffffff" opacity="0.8"/>
                        <circle cx="-2" cy="2" r="3" fill="#3559a8"/>
                    </g>
                    <path d="M-138 19c27 17 56 18 86 0" stroke="#5a2e33" stroke-width="7" stroke-linecap="round" fill="none"/>
                    <circle cx="-134" cy="50" r="26" fill="#ffc9d2" opacity="0.6"/>
                    <circle cx="84" cy="45" r="24" fill="#ffc9d2" opacity="0.6"/>
                    <ellipse cx="8" cy="32" rx="8" ry="11" fill="#ffffff" opacity="0.8"/>
                    <path d="M-24 79c18 10 36 10 55 0" stroke="#d78f9c" stroke-width="4" stroke-linecap="round" fill="none"/>

                    <g transform="translate(8 182)">
                        <path d="M-116 -26c28-13 56-7 79 22-7 20-18 34-34 43-25-7-45-22-61-43 3-7 9-15 16-22z" fill="#ff8dbe"/>
                        <path d="M26 -29c-28-13-56-7-79 22 7 20 18 34 34 43 25-7 45-22 61-43-3-7-9-15-16-22z" fill="#ffb5d6"/>
                        <ellipse cx="-18" cy="4" rx="46" ry="34" fill="#ff9aca"/>
                    </g>

                    <g transform="translate(22 176)">
                        <path d="M-5 -56h36l16 118h-36z" fill="#ffe7f3" stroke="#ddc5d4" stroke-width="3"/>
                        <path d="M9 -24h8v58h-8z" fill="#d9d4da"/>
                        <path d="M-4 62h32c14 0 18 8 12 21l-12 144c-2 15-11 22-24 22-12 0-19-7-17-21l9-144c1-14 8-22 20-22z" fill="#f7d58d" stroke="#c9a463" stroke-width="3"/>
                    </g>

                    <g transform="translate(-10 240)">
                        <path d="M-142 84c17-62 57-110 120-126 31-8 64 5 93 22 23 14 52 22 80 18 26-3 48-18 71-31 39-22 79-27 119-13-8 50-31 87-73 108-56 29-142 43-227 43-78 0-132-4-183-21z" fill="url(#dressGrad)"/>
                        <path d="M-22 34c18 33 33 74 20 110-33 17-63 13-91-10 7-46 31-79 71-100z" fill="#fff8fb"/>
                        <path d="M-111 110c17-30 38-40 66-32-1 34-8 59-26 78-22-4-39-18-53-46z" fill="#ffd0e4"/>
                    </g>

                    <g transform="translate(-182 -154)">
                        <path d="M0 0c18-15 35-15 56 0-3 26-16 43-41 51C3 37-2 19 0 0z" fill="#f593c4"/>
                        <circle cx="16" cy="14" r="5" fill="#d36ca0"/>
                    </g>
                    <g transform="translate(176 -148)">
                        <path d="M0 0c18-15 35-15 56 0-3 26-16 43-41 51C3 37-2 19 0 0z" fill="#f593c4"/>
                        <circle cx="16" cy="14" r="5" fill="#d36ca0"/>
                    </g>
                </g>

                <g transform="translate(58 54)" fill="#ff7e98">
                    <path d="M0 18c0-11 8-18 18-18 7 0 12 3 16 9 4-6 10-9 17-9 9 0 17 6 17 17 0 22-23 38-34 46C20 53 0 38 0 18z"/>
                    <path d="M60 8c0-7 5-12 12-12 5 0 9 2 11 6 2-4 6-6 11-6 7 0 12 5 12 12 0 15-15 26-23 31C75 34 60 24 60 8z" opacity="0.68"/>
                </g>
                <g transform="translate(40 786)" fill="#ff8cab">
                    <path d="M0 18c0-11 8-18 18-18 7 0 12 3 16 9 4-6 10-9 17-9 9 0 17 6 17 17 0 22-23 38-34 46C20 53 0 38 0 18z"/>
                    <path d="M60 8c0-7 5-12 12-12 5 0 9 2 11 6 2-4 6-6 11-6 7 0 12 5 12 12 0 15-15 26-23 31C75 34 60 24 60 8z" opacity="0.68"/>
                </g>
            </svg>
        </div>
    </section>
</div>
</body>
</html>
