<?php

declare(strict_types=1);

namespace OilApp;

final class DatedBrentService
{
    public function __construct(
        private readonly DatedBrentScraper $scraper,
        private readonly DatedBrentRepository $repository
    ) {
    }

    public function syncHistory(int $limit = 365): array
    {
        $records = $this->scraper->fetchHistory($limit);
        $this->repository->upsertMany($records);

        return $records;
    }
}
