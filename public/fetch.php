<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\Database;
use OilApp\PriceRepository;
use OilApp\PriceScraper;
use OilApp\PriceService;

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');

try {
    $connection = Database::connect($config);
    $pdo = $connection['pdo'];
    $driver = $connection['driver'];
    Database::ensureSchema($pdo, $driver);

    $service = new PriceService(
        new PriceScraper($config),
        new PriceRepository($pdo, $driver)
    );

    $record = $service->fetchAndStore();

    $message = sprintf(
        '已抓取 %s 的 OQD Marker Price：%s',
        $record['price_date'],
        $record['marker_price']
    );
    if ($connection['warning']) {
        $message .= '（已切換為本地 SQLite 測試儲存）';
    }

    header('Location: ' . ($basePath ?: '') . '/?message=' . urlencode($message));
    exit;
} catch (Throwable $e) {
    header('Location: ' . ($basePath ?: '') . '/?error=' . urlencode($e->getMessage()));
    exit;
}