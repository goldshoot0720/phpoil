<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use OilApp\Database;
use OilApp\DatedBrentRepository;
use OilApp\DatedBrentScraper;
use OilApp\DatedBrentService;
use OilApp\PriceRepository;
use OilApp\PriceScraper;
use OilApp\PriceService;

try {
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

    echo sprintf(
        "[%s] Stored OQD Marker Price %s for %s using %s\n",
        date('Y-m-d H:i:s'),
        $priceRecord['marker_price'],
        $priceRecord['price_date'],
        strtoupper($driver)
    );

    if ($latestBrent) {
        echo sprintf(
            "[%s] Synced Dated Brent Spot %s for %s\n",
            date('Y-m-d H:i:s'),
            $latestBrent['spot_price'],
            $latestBrent['price_date']
        );
    }

    if ($connection['warning']) {
        echo '[WARN] ' . $connection['warning'] . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
