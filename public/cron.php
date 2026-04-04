<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\CronRunLogger;
use OilApp\Database;
use OilApp\DatedBrentRepository;
use OilApp\DatedBrentScraper;
use OilApp\DatedBrentService;
use OilApp\DramDdr5PriceRepository;
use OilApp\DramDdr5PriceScraper;
use OilApp\DramDdr5PriceService;
use OilApp\PriceRepository;
use OilApp\PriceScraper;
use OilApp\PriceService;

header('Content-Type: application/json; charset=utf-8');

$logger = new CronRunLogger(__DIR__ . '/../storage/scheduled-fetch.log', __DIR__ . '/../storage/cron_status.json');

try {
    $incomingKey = $_GET['key'] ?? '';
    if ($incomingKey !== $config['scraper']['cron_key']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid cron key.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $connection = Database::connect($config);
    $pdo = $connection['pdo'];
    $driver = $connection['driver'];
    Database::ensureSchema($pdo, $driver);

    $priceService = new PriceService(
        new PriceScraper($config),
        new PriceRepository($pdo, $driver)
    );
    $priceRecord = $priceService->fetchAndStore();

    $datedBrentService = new DatedBrentService(
        new DatedBrentScraper($config),
        new DatedBrentRepository($pdo, $driver)
    );
    $brentRecords = $datedBrentService->syncHistory();
    $latestBrent = end($brentRecords) ?: null;

    $dramService = new DramDdr5PriceService(
        new DramDdr5PriceScraper($config),
        new DramDdr5PriceRepository($pdo, $driver)
    );
    $dramRecord = $dramService->fetchAndStore();

    $status = $logger->log('oil_price_cron', true, 'Cron endpoint completed successfully.', [
        'driver' => $driver,
        'warning' => $connection['warning'],
        'record_date' => $priceRecord['price_date'] ?? null,
        'dated_brent_date' => $latestBrent['price_date'] ?? null,
        'dram_ddr5_date' => $dramRecord['snapshot_date'] ?? null,
    ]);

    echo json_encode([
        'ok' => true,
        'driver' => $driver,
        'warning' => $connection['warning'],
        'record' => $priceRecord,
        'dated_brent' => $latestBrent,
        'dram_ddr5' => $dramRecord,
        'status' => $status,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}