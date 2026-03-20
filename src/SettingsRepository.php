<?php

declare(strict_types=1);

namespace OilApp;

final class SettingsRepository
{
    public function __construct(
        private readonly string $path
    ) {
    }

    public function all(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $raw = file_get_contents($this->path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    public function saveGithubToken(string $token): void
    {
        $settings = $this->all();
        $settings['github']['token'] = $token;
        $this->write($settings);
    }

    public function clearGithubToken(): void
    {
        $settings = $this->all();
        unset($settings['github']['token']);
        if (isset($settings['github']) && $settings['github'] === []) {
            unset($settings['github']);
        }

        $this->write($settings);
    }

    private function write(array $settings): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $this->path,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
