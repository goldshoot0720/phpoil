<?php

declare(strict_types=1);

namespace OilApp;

final class CommitStatsCacheRepository
{
    public function __construct(
        private readonly string $path
    ) {
    }

    public function load(): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }

        $raw = file_get_contents($this->path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    public function save(array $payload): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $this->path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function clear(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }
}
