<?php

declare(strict_types=1);

namespace OilApp;

use PDO;

final class DatedBrentRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $driver
    ) {
    }

    public function upsert(array $record): void
    {
        $sql = $this->driver === 'sqlite'
            ? <<<SQL
                INSERT INTO dated_brent_prices (
                    price_date,
                    spot_price,
                    source_url,
                    fetched_at,
                    raw_label
                ) VALUES (
                    :price_date,
                    :spot_price,
                    :source_url,
                    :fetched_at,
                    :raw_label
                )
                ON CONFLICT(price_date) DO UPDATE SET
                    spot_price = excluded.spot_price,
                    source_url = excluded.source_url,
                    fetched_at = excluded.fetched_at,
                    raw_label = excluded.raw_label,
                    updated_at = CURRENT_TIMESTAMP
                SQL
            : <<<SQL
                INSERT INTO dated_brent_prices (
                    price_date,
                    spot_price,
                    source_url,
                    fetched_at,
                    raw_label
                ) VALUES (
                    :price_date,
                    :spot_price,
                    :source_url,
                    :fetched_at,
                    :raw_label
                )
                ON DUPLICATE KEY UPDATE
                    spot_price = VALUES(spot_price),
                    source_url = VALUES(source_url),
                    fetched_at = VALUES(fetched_at),
                    raw_label = VALUES(raw_label)
                SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($record);
    }

    public function upsertMany(array $records): void
    {
        foreach ($records as $record) {
            $this->upsert($record);
        }
    }

    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT price_date, spot_price, fetched_at, raw_label FROM dated_brent_prices ORDER BY price_date ASC'
        );

        return $stmt->fetchAll();
    }

    public function latest(): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT price_date, spot_price, fetched_at, raw_label FROM dated_brent_prices ORDER BY price_date DESC LIMIT 1'
        );

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function latestFetchDate(): ?string
    {
        $sql = $this->driver === 'sqlite'
            ? "SELECT substr(fetched_at, 1, 10) AS fetch_date FROM dated_brent_prices ORDER BY fetched_at DESC LIMIT 1"
            : "SELECT DATE_FORMAT(fetched_at, '%Y-%m-%d') AS fetch_date FROM dated_brent_prices ORDER BY fetched_at DESC LIMIT 1";

        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch();

        return $row['fetch_date'] ?? null;
    }
}
