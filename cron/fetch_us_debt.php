<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\Database;
use OilApp\USDebtRepository;
use OilApp\USDebtScraper;
use OilApp\USDebtService;

try {
    $connection = Database::connect($config);
    $pdo = $connection['pdo'];
    $driver = $connection['driver'];
    Database::ensureSchema($pdo, $driver);

    $service = new USDebtService(
        new USDebtScraper($config),
        new USDebtRepository($pdo, $driver)
    );

    $record = $service->fetchAndStore();

    echo sprintf(
        "[%s] Stored US National Debt %s for %s using %s\n",
        date('Y-m-d H:i:s'),
        $record['debt_amount'],
        $record['snapshot_date'],
        strtoupper($driver)
    );
    if ($connection['warning']) {
        echo '[WARN] ' . $connection['warning'] . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
