<?php

declare(strict_types=1);

namespace OilApp;

use PDO;

final class PriceRepository
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
                INSERT INTO oil_prices (
                    price_date,
                    marker_price,
                    source_url,
                    fetched_at,
                    raw_label
                ) VALUES (
                    :price_date,
                    :marker_price,
                    :source_url,
                    :fetched_at,
                    :raw_label
                )
                ON CONFLICT(price_date) DO UPDATE SET
                    marker_price = excluded.marker_price,
                    source_url = excluded.source_url,
                    fetched_at = excluded.fetched_at,
                    raw_label = excluded.raw_label,
                    updated_at = CURRENT_TIMESTAMP
                SQL
            : <<<SQL
                INSERT INTO oil_prices (
                    price_date,
                    marker_price,
                    source_url,
                    fetched_at,
                    raw_label
                ) VALUES (
                    :price_date,
                    :marker_price,
                    :source_url,
                    :fetched_at,
                    :raw_label
                )
                ON DUPLICATE KEY UPDATE
                    marker_price = VALUES(marker_price),
                    source_url = VALUES(source_url),
                    fetched_at = VALUES(fetched_at),
                    raw_label = VALUES(raw_label)
                SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($record);
    }

    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT price_date, marker_price, fetched_at, raw_label FROM oil_prices ORDER BY price_date ASC'
        );

        return $stmt->fetchAll();
    }

    public function latest(): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT price_date, marker_price, fetched_at, raw_label FROM oil_prices ORDER BY price_date DESC LIMIT 1'
        );

        $row = $stmt->fetch();

        return $row ?: null;
    }
}