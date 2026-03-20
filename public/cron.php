<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\Database;
use OilApp\PriceRepository;
use OilApp\PriceScraper;
use OilApp\PriceService;

header('Content-Type: application/json; charset=utf-8');

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

    $service = new PriceService(
        new PriceScraper($config),
        new PriceRepository($pdo, $driver)
    );

    $record = $service->fetchAndStore();

    echo json_encode([
        'ok' => true,
        'driver' => $driver,
        'warning' => $connection['warning'],
        'record' => $record,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}