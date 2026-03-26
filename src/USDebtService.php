<?php

declare(strict_types=1);

namespace OilApp;

final class USDebtService
{
    public function __construct(
        private readonly USDebtScraper $scraper,
        private readonly USDebtRepository $repository
    ) {
    }

    public function fetchAndStore(): array
    {
        $record = $this->scraper->fetchLatest();
        $this->repository->upsert($record);

        return $record;
    }
}
