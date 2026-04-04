<?php

declare(strict_types=1);

return [
    'app_name' => 'Oil Price Monitor',
    'timezone' => 'Asia/Taipei',
    'db' => [
        'host' => 'sql301.infinityfree.com',
        'port' => 3306,
        'database' => 'if0_38435166_goldshoot0720',
        'username' => 'if0_38435166',
        'password' => 'gf0Tagood129',
        'charset' => 'utf8mb4',
        'sqlite_fallback' => __DIR__ . '/storage/oil_prices.sqlite',
    ],
    'dated_brent' => [
        'source_url' => 'https://datahub.io/core/oil-prices/_r/-/data/brent-daily.csv',
    ],
    'scraper' => [
        'source_url' => 'https://www.gulfmerc.com/gme-product-services/gme-ace',
        'cron_key' => 'oil-monitor-20260319',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0 Safari/537.36',
    ],
    'github' => [
        'username' => 'goldshoot0720',
        'token' => '',
        'api_base' => 'https://api.github.com',
    ],
];
