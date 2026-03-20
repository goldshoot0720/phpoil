<?php

declare(strict_types=1);

namespace OilApp;

use RuntimeException;

final class CommitStatsService
{
    private const PER_PAGE = 100;

    public function __construct(
        private readonly array $config
    ) {
    }

    public function fetchSummary(): array
    {
        $github = $this->config['github'] ?? [];
        $username = trim((string) ($github['username'] ?? ''));

        if ($username === '') {
            return [
                'ok' => false,
                'message' => '尚未設定 GitHub 使用者名稱，請先在 config.php 填入 username。',
                'username' => null,
                'repo_count' => 0,
                'total_commits' => 0,
                'top10_total_commits' => 0,
                'top_repositories' => [],
                'latest_commit_at' => null,
            ];
        }

        $repositories = $this->fetchAllRepositories($username);

        $repoStats = [];
        $totalCommits = 0;
        $latestCommitAt = null;
        $totalRepositories = count($repositories);

        foreach ($repositories as $repository) {
            $name = trim((string) ($repository['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $defaultBranch = trim((string) ($repository['default_branch'] ?? 'main'));
            $commitSummary = $this->fetchRepositoryCommitCount($username, $name, $defaultBranch);
            if ($commitSummary === null) {
                continue;
            }

            $repoStats[] = [
                'name' => $name,
                'full_name' => (string) ($repository['full_name'] ?? ($username . '/' . $name)),
                'html_url' => (string) ($repository['html_url'] ?? ''),
                'default_branch' => $defaultBranch,
                'commits' => $commitSummary['commits'],
                'latest_commit_at' => $commitSummary['latest_commit_at'],
            ];
            $totalCommits += $commitSummary['commits'];

            if (
                $commitSummary['latest_commit_at'] !== null
                && ($latestCommitAt === null || strtotime($commitSummary['latest_commit_at']) > strtotime($latestCommitAt))
            ) {
                $latestCommitAt = $commitSummary['latest_commit_at'];
            }
        }

        usort($repoStats, static fn (array $left, array $right): int => $right['commits'] <=> $left['commits']);
        $topRepositories = array_slice($repoStats, 0, 10);
        $top10TotalCommits = array_sum(array_map(
            static fn (array $repo): int => (int) $repo['commits'],
            $topRepositories
        ));

        return [
            'ok' => true,
            'message' => null,
            'username' => $username,
            'repo_count' => $totalRepositories,
            'counted_repo_count' => count($repoStats),
            'total_commits' => $totalCommits,
            'top10_total_commits' => $top10TotalCommits,
            'top_repositories' => $topRepositories,
            'latest_commit_at' => $latestCommitAt,
        ];
    }

    private function fetchAllRepositories(string $username): array
    {
        $repositories = [];
        $page = 1;

        do {
            $batch = $this->request(
                sprintf(
                    '/users/%s/repos?per_page=%d&sort=updated&page=%d',
                    rawurlencode($username),
                    self::PER_PAGE,
                    $page
                ),
                $headers
            );

            if ($batch === []) {
                break;
            }

            $repositories = array_merge($repositories, $batch);
            $page++;
            $hasNextPage = $this->hasNextPage($headers);
        } while ($hasNextPage);

        return $repositories;
    }

    private function fetchRepositoryCommitCount(string $owner, string $repo, string $branch): ?array
    {
        try {
            $commits = $this->request(
                sprintf(
                    '/repos/%s/%s/commits?sha=%s&per_page=1&page=1',
                    rawurlencode($owner),
                    rawurlencode($repo),
                    rawurlencode($branch)
                ),
                $headers
            );
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'HTTP 409')) {
                return null;
            }

            throw $exception;
        }

        $commitCount = $this->extractLastPage($headers);
        if ($commitCount === null) {
            $commitCount = count($commits);
        }

        return [
            'commits' => $commitCount,
            'latest_commit_at' => $commits[0]['commit']['author']['date'] ?? null,
        ];
    }

    private function request(string $path, ?array &$responseHeaders = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required to fetch GitHub commit statistics.');
        }

        $github = $this->config['github'] ?? [];
        $apiBase = rtrim((string) ($github['api_base'] ?? 'https://api.github.com'), '/');
        $token = trim((string) ($github['token'] ?? ''));
        $url = $apiBase . $path;

        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: phpoil-commit-stats',
        ];

        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $responseHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
                $length = strlen($headerLine);
                $header = explode(':', $headerLine, 2);
                if (count($header) === 2) {
                    $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
                }

                return $length;
            },
        ]);

        $body = curl_exec($ch);

        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Failed to contact GitHub API: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            $message = 'GitHub API returned HTTP ' . $statusCode . '.';
            if ($statusCode === 403) {
                $message .= ' Public requests may be rate-limited; set github.token in config.php and try again.';
            }

            throw new RuntimeException($message);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON returned from GitHub API.');
        }

        return $data;
    }

    private function extractLastPage(array $headers): ?int
    {
        $linkHeader = $headers['link'] ?? null;
        if (!is_string($linkHeader) || $linkHeader === '') {
            return null;
        }

        if (preg_match('/[?&]page=(\d+)>;\s*rel="last"/', $linkHeader, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function hasNextPage(array $headers): bool
    {
        $linkHeader = $headers['link'] ?? null;

        return is_string($linkHeader) && str_contains($linkHeader, 'rel="next"');
    }
}
