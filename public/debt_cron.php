<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\CronRunLogger;
use OilApp\Database;
use OilApp\USDebtRepository;
use OilApp\USDebtScraper;
use OilApp\USDebtService;

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

    $service = new USDebtService(
        new USDebtScraper($config),
        new USDebtRepository($pdo, $driver)
    );

    $record = $service->fetchAndStore();

    $status = $logger->log('us_debt_cron', true, 'Cron endpoint completed successfully.', [
        'driver' => $driver,
        'warning' => $connection['warning'],
        'record_date' => $record['snapshot_date'] ?? null,
    ]);

    echo json_encode([
        'ok' => true,
        'driver' => $driver,
        'warning' => $connection['warning'],
        'record' => $record,
        'status' => $status,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $status = $logger->log('us_debt_cron', false, $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage(), 'status' => $status], JSON_UNESCAPED_UNICODE);
}
