<?php

declare(strict_types=1);

namespace OilApp;

final class CronRunLogger
{
    public function __construct(
        private readonly string $logPath,
        private readonly string $statusPath
    ) {
    }

    public function log(string $job, bool $ok, string $message, array $context = []): array
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf(
            "[%s] [%s] [%s] %s%s",
            $timestamp,
            $job,
            $ok ? 'OK' : 'ERROR',
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        file_put_contents($this->logPath, $line . PHP_EOL, FILE_APPEND);

        $status = $this->readStatus();
        $status[$job] = [
            'job' => $job,
            'ok' => $ok,
            'message' => $message,
            'context' => $context,
            'timestamp' => $timestamp,
        ];

        file_put_contents(
            $this->statusPath,
            json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $status[$job];
    }

    private function readStatus(): array
    {
        if (!is_file($this->statusPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->statusPath), true);

        return is_array($decoded) ? $decoded : [];
    }
}