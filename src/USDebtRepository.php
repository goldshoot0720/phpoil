<?php

declare(strict_types=1);

namespace OilApp;

use PDO;

final class USDebtRepository
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
                INSERT INTO us_debt_history (
                    snapshot_date,
                    debt_amount,
                    debt_rate_per_second,
                    source_url,
                    fetched_at,
                    source_layer,
                    source_element_id,
                    raw_label
                ) VALUES (
                    :snapshot_date,
                    :debt_amount,
                    :debt_rate_per_second,
                    :source_url,
                    :fetched_at,
                    :source_layer,
                    :source_element_id,
                    :raw_label
                )
                ON CONFLICT(snapshot_date) DO UPDATE SET
                    debt_amount = excluded.debt_amount,
                    debt_rate_per_second = excluded.debt_rate_per_second,
                    source_url = excluded.source_url,
                    fetched_at = excluded.fetched_at,
                    source_layer = excluded.source_layer,
                    source_element_id = excluded.source_element_id,
                    raw_label = excluded.raw_label,
                    updated_at = CURRENT_TIMESTAMP
                SQL
            : <<<SQL
                INSERT INTO us_debt_history (
                    snapshot_date,
                    debt_amount,
                    debt_rate_per_second,
                    source_url,
                    fetched_at,
                    source_layer,
                    source_element_id,
                    raw_label
                ) VALUES (
                    :snapshot_date,
                    :debt_amount,
                    :debt_rate_per_second,
                    :source_url,
                    :fetched_at,
                    :source_layer,
                    :source_element_id,
                    :raw_label
                )
                ON DUPLICATE KEY UPDATE
                    debt_amount = VALUES(debt_amount),
                    debt_rate_per_second = VALUES(debt_rate_per_second),
                    source_url = VALUES(source_url),
                    fetched_at = VALUES(fetched_at),
                    source_layer = VALUES(source_layer),
                    source_element_id = VALUES(source_element_id),
                    raw_label = VALUES(raw_label)
                SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($record);
    }

    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT snapshot_date, debt_amount, debt_rate_per_second, fetched_at FROM us_debt_history ORDER BY snapshot_date ASC'
        );

        return $stmt->fetchAll();
    }

    public function latest(): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT snapshot_date, debt_amount, debt_rate_per_second, fetched_at, source_url, source_layer, source_element_id, raw_label FROM us_debt_history ORDER BY snapshot_date DESC LIMIT 1'
        );

        $row = $stmt->fetch();

        return $row ?: null;
    }
}
