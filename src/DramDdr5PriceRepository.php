<?php

declare(strict_types=1);

namespace OilApp;

use PDO;

final class DramDdr5PriceRepository
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
                INSERT INTO dram_ddr5_module_prices (
                    snapshot_date,
                    item_name,
                    weekly_high,
                    weekly_low,
                    session_high,
                    session_low,
                    session_average,
                    average_change,
                    source_url,
                    fetched_at,
                    raw_label
                ) VALUES (
                    :snapshot_date,
                    :item_name,
                    :weekly_high,
                    :weekly_low,
                    :session_high,
                    :session_low,
                    :session_average,
                    :average_change,
                    :source_url,
                    :fetched_at,
                    :raw_label
                )
                ON CONFLICT(snapshot_date) DO UPDATE SET
                    item_name = excluded.item_name,
                    weekly_high = excluded.weekly_high,
                    weekly_low = excluded.weekly_low,
                    session_high = excluded.session_high,
                    session_low = excluded.session_low,
                    session_average = excluded.session_average,
                    average_change = excluded.average_change,
                    source_url = excluded.source_url,
                    fetched_at = excluded.fetched_at,
                    raw_label = excluded.raw_label,
                    updated_at = CURRENT_TIMESTAMP
                SQL
            : <<<SQL
                INSERT INTO dram_ddr5_module_prices (
                    snapshot_date,
                    item_name,
                    weekly_high,
                    weekly_low,
                    session_high,
                    session_low,
                    session_average,
                    average_change,
                    source_url,
                    fetched_at,
                    raw_label
                ) VALUES (
                    :snapshot_date,
                    :item_name,
                    :weekly_high,
                    :weekly_low,
                    :session_high,
                    :session_low,
                    :session_average,
                    :average_change,
                    :source_url,
                    :fetched_at,
                    :raw_label
                )
                ON DUPLICATE KEY UPDATE
                    item_name = VALUES(item_name),
                    weekly_high = VALUES(weekly_high),
                    weekly_low = VALUES(weekly_low),
                    session_high = VALUES(session_high),
                    session_low = VALUES(session_low),
                    session_average = VALUES(session_average),
                    average_change = VALUES(average_change),
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
            'SELECT snapshot_date, item_name, weekly_high, weekly_low, session_high, session_low, session_average, average_change, fetched_at, raw_label FROM dram_ddr5_module_prices ORDER BY snapshot_date ASC'
        );

        return $stmt->fetchAll();
    }

    public function latest(): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT snapshot_date, item_name, weekly_high, weekly_low, session_high, session_low, session_average, average_change, fetched_at, raw_label FROM dram_ddr5_module_prices ORDER BY snapshot_date DESC LIMIT 1'
        );

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function latestFetchDate(): ?string
    {
        $sql = $this->driver === 'sqlite'
            ? "SELECT substr(fetched_at, 1, 10) AS fetch_date FROM dram_ddr5_module_prices ORDER BY fetched_at DESC LIMIT 1"
            : "SELECT DATE_FORMAT(fetched_at, '%Y-%m-%d') AS fetch_date FROM dram_ddr5_module_prices ORDER BY fetched_at DESC LIMIT 1";

        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch();

        return $row['fetch_date'] ?? null;
    }
}