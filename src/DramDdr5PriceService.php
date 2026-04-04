<?php

declare(strict_types=1);

namespace OilApp;

final class DramDdr5PriceService
{
    public function __construct(
        private readonly DramDdr5PriceScraper $scraper,
        private readonly DramDdr5PriceRepository $repository
    ) {
    }

    public function fetchAndStore(): array
    {
        $record = $this->scraper->fetchLatest();
        $this->repository->upsert($record);

        return $record;
    }
}