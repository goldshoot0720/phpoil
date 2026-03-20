<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\Database;
use OilApp\PriceRepository;
use OilApp\PriceScraper;
use OilApp\PriceService;

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

    echo sprintf(
        "[%s] Stored OQD Marker Price %s for %s using %s\n",
        date('Y-m-d H:i:s'),
        $record['marker_price'],
        $record['price_date'],
        strtoupper($driver)
    );
    if ($connection['warning']) {
        echo '[WARN] ' . $connection['warning'] . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}