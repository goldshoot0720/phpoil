<?php

declare(strict_types=1);

return [
    'app_name' => 'Oil Price Monitor',
    'timezone' => 'Asia/Taipei',
    'db' => [
        'host' => 'sql301.infinityfree.com',
        'port' => 3306,
        'database' => 'if0_XXXXXXXX_goldshoot0720',
        'username' => 'if0_XXXXXXXX',
        'password' => 'YOUR_MYSQL_PASSWORD',
        'charset' => 'utf8mb4',
        'sqlite_fallback' => __DIR__ . '/storage/oil_prices.sqlite',
    ],
    'scraper' => [
        'source_url' => 'https://www.gulfmerc.com/gme-product-services/gme-ace',
        'cron_key' => 'CHANGE_THIS_CRON_KEY',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0 Safari/537.36',
    ],
];