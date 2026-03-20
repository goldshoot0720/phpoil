<?php

declare(strict_types=1);

namespace OilApp;

final class PriceService
{
    public function __construct(
        private readonly PriceScraper $scraper,
        private readonly PriceRepository $repository
    ) {
    }

    public function fetchAndStore(): array
    {
        $record = $this->scraper->fetchLatest();
        $this->repository->upsert($record);

        return $record;
    }
}
