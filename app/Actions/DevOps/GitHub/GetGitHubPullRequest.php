<?php

/*
 * Author Louis Perez
 * Created on 30-09-2026
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\DevOps\GitHub;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class GetGitHubPullRequest
{
    use AsAction;

    private const URL_PATTERN = '#^https?://(?:www\.)?github\.com/([\w.-]+)/([\w.-]+)/pull/(\d+)(?:[/?\#].*)?$#i';

    /**
     * Reads a pull request from GitHub. A good answer is kept for a couple of minutes, so a ticket
     * opened by several people at once asks GitHub once. Anything GitHub will not answer comes
     * back as a RuntimeException whose message can be shown to the person as it is.
     *
     * @return array{number: int, title: string, url: string, repository: string, state: string, state_label: string, body: string|null, author: array{login: string|null, avatar: string|null, url: string|null}, created_at: string|null, merged_at: string|null, closed_at: string|null}
     */
    public function handle(string $url, bool $fresh = false): array
    {
        $parts = self::parseUrl($url);

        if (!$parts) {
            throw new RuntimeException(__('That is not a GitHub pull request link, it should look like https://github.com/owner/repo/pull/123'));
        }

        [$owner, $repo, $number] = $parts;
        $cacheKey = "github:pull:$owner/$repo/$number";

        if (!$fresh && $cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $pullRequest = $this->fetch($owner, $repo, $number);
        Cache::put($cacheKey, $pullRequest, now()->addMinutes(2));

        return $pullRequest;
    }

    /**
     * @return array{0: string, 1: string, 2: int}|null
     */
    public static function parseUrl(string $url): ?array
    {
        if (!preg_match(self::URL_PATTERN, trim($url), $matches)) {
            return null;
        }

        return [$matches[1], $matches[2], (int) $matches[3]];
    }

    /**
     * The same pull request always saved the same way, without the /files or #comment tail
     * that comes along when the link is copied from another tab.
     */
    public static function normaliseUrl(string $url): ?string
    {
        $parts = self::parseUrl($url);

        return $parts ? "https://github.com/$parts[0]/$parts[1]/pull/$parts[2]" : null;
    }

    /**
     * The commits of the pull request, oldest first as GitHub lists them, up to the first 100.
     *
     * @return array<int, array{sha: string, short_sha: string, subject: string, message: string, author: string|null, author_avatar: string|null, date: string|null, url: string}>
     */
    public function commits(string $url): array
    {
        $parts = self::parseUrl($url);

        if (!$parts) {
            throw new RuntimeException(__('That is not a GitHub pull request link, it should look like https://github.com/owner/repo/pull/123'));
        }

        [$owner, $repo, $number] = $parts;

        return Cache::remember(
            "github:pull:$owner/$repo/$number:commits",
            now()->addMinutes(2),
            fn () => collect($this->requestGitHub("repos/$owner/$repo/pulls/$number/commits", ['per_page' => 100])->json())
                ->map(fn (array $commit) => [
                    'sha'           => $commit['sha'],
                    'short_sha'     => substr($commit['sha'], 0, 7),
                    'subject'       => strtok((string) data_get($commit, 'commit.message'), "\n") ?: '',
                    'message'       => (string) data_get($commit, 'commit.message'),
                    'author'        => data_get($commit, 'author.login') ?? data_get($commit, 'commit.author.name'),
                    'author_avatar' => data_get($commit, 'author.avatar_url'),
                    'date'          => data_get($commit, 'commit.author.date'),
                    'url'           => (string) data_get($commit, 'html_url'),
                ])
                ->all()
        );
    }

    private function requestGitHub(string $path, array $query = []): Response
    {
        $request = Http::connectTimeout(5)->timeout(10)->acceptJson();

        if ($token = config('services.github.token')) {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->get("https://api.github.com/$path", $query);
        } catch (ConnectionException) {
            throw new RuntimeException(__('GitHub could not be reached, try again in a moment'));
        }

        if ($response->status() === 404) {
            throw new RuntimeException(__('Pull request not found, or GitHub does not let us see it'));
        }

        if (in_array($response->status(), [401, 403, 429], true)) {
            throw new RuntimeException(__('GitHub refused to answer, try again later'));
        }

        if (!$response->successful()) {
            throw new RuntimeException(__('GitHub could not be reached, try again in a moment'));
        }

        return $response;
    }

    private function fetch(string $owner, string $repo, int $number): array
    {
        $response = $this->requestGitHub("repos/$owner/$repo/pulls/$number");

        $state = match (true) {
            (bool) $response->json('merged')     => 'merged',
            $response->json('state') === 'closed' => 'closed',
            (bool) $response->json('draft')      => 'draft',
            default                              => 'open',
        };

        return [
            'number'      => (int) $response->json('number'),
            'title'       => (string) $response->json('title'),
            'url'         => (string) $response->json('html_url'),
            'repository'  => "$owner/$repo",
            'state'       => $state,
            'state_label' => [
                'merged' => __('Merged'),
                'closed' => __('Closed'),
                'draft'  => __('Draft'),
                'open'   => __('Open'),
            ][$state],
            'body'        => $response->json('body'),
            'author'      => [
                'login'  => $response->json('user.login'),
                'avatar' => $response->json('user.avatar_url'),
                'url'    => $response->json('user.html_url'),
            ],
            'created_at'  => $response->json('created_at'),
            'merged_at'   => $response->json('merged_at'),
            'closed_at'   => $response->json('closed_at'),
        ];
    }
}
