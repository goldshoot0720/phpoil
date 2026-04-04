<?php

declare(strict_types=1);

namespace OilApp;

use PDO;
use PDOException;

final class Database
{
    public static function connect(array $config): array
    {
        $db = $config['db'];

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['database'],
                $db['charset']
            );

            $pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            return [
                'pdo' => $pdo,
                'driver' => 'mysql',
                'warning' => null,
            ];
        } catch (PDOException $exception) {
            $storageDir = dirname($db['sqlite_fallback']);
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0777, true);
            }

            $pdo = new PDO('sqlite:' . $db['sqlite_fallback']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return [
                'pdo' => $pdo,
                'driver' => 'sqlite',
                'warning' => 'Remote MySQL unavailable. Using local SQLite fallback for testing.',
            ];
        }
    }

    public static function ensureSchema(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec(
                <<<SQL
                CREATE TABLE IF NOT EXISTS oil_prices (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    price_date TEXT NOT NULL UNIQUE,
                    marker_price REAL NOT NULL,
                    source_url TEXT NOT NULL,
                    fetched_at TEXT NOT NULL,
                    raw_label TEXT NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
                SQL
            );

            $pdo->exec(
                <<<SQL
                CREATE TABLE IF NOT EXISTS us_debt_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    snapshot_date TEXT NOT NULL UNIQUE,
                    debt_amount REAL NOT NULL,
                    debt_rate_per_second REAL NOT NULL,
                    source_url TEXT NOT NULL,
                    fetched_at TEXT NOT NULL,
                    source_layer TEXT NOT NULL,
                    source_element_id TEXT NOT NULL,
                    raw_label TEXT NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
                SQL
            );

            $pdo->exec(
                <<<SQL
                CREATE TABLE IF NOT EXISTS dated_brent_prices (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    price_date TEXT NOT NULL UNIQUE,
                    spot_price REAL NOT NULL,
                    source_url TEXT NOT NULL,
                    fetched_at TEXT NOT NULL,
                    raw_label TEXT NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
                SQL
            );

            return;
        }

        $pdo->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS oil_prices (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                price_date DATE NOT NULL,
                marker_price DECIMAL(10, 2) NOT NULL,
                source_url VARCHAR(255) NOT NULL,
                fetched_at DATETIME NOT NULL,
                raw_label VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_price_date (price_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );

        $pdo->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS us_debt_history (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                snapshot_date DATE NOT NULL,
                debt_amount DECIMAL(18, 2) NOT NULL,
                debt_rate_per_second DECIMAL(18, 6) NOT NULL,
                source_url VARCHAR(255) NOT NULL,
                fetched_at DATETIME NOT NULL,
                source_layer VARCHAR(32) NOT NULL,
                source_element_id VARCHAR(64) NOT NULL,
                raw_label VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_snapshot_date (snapshot_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );

        $pdo->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS dated_brent_prices (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                price_date DATE NOT NULL,
                spot_price DECIMAL(10, 2) NOT NULL,
                source_url VARCHAR(255) NOT NULL,
                fetched_at DATETIME NOT NULL,
                raw_label VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_price_date (price_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );
    }
}
