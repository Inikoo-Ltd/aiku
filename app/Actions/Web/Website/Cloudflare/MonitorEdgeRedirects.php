<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 11:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Cloudflare;

use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * Cloudflare keeps storefront 301s for an hour. For each live storefront this takes the paths whose
 * redirect Cloudflare answered from its copy most in the last hour (the Free plan's analytics have no
 * query string, so the plain path is checked) and asks for them twice: as a browser
 * (Cloudflare's copy) and as the cache warmer, which the edge rule skips (what our servers say
 * now). A different Location means the edge copy is stale, and following the browser's chain must
 * end on a 200 within a few hops: anything else (a loop, a 404) is what visitors get. Problems go
 * to Discord with the fix: php artisan cloudflare:purge-host <slug>.
 */
class MonitorEdgeRedirects
{
    use AsAction;

    public const int PATHS_PER_WEBSITE = 10;

    public const int MAX_HOPS = 5;

    public const string BROWSER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';

    public const string ORIGIN_AGENT = 'aiku-cache-warmer';

    public string $commandSignature = 'monitor:edge_redirects {--website= : Website slug}';

    public string $commandDescription = 'Check the storefront redirects Cloudflare serves from its copy against what our servers answer now';

    /**
     * @return array{checked: int, problems: array<int, string>, errors: array<int, string>}
     */
    public function handle(?string $websiteSlug = null, ?Command $command = null): array
    {
        Nightwatch::dontSample();

        $checked  = 0;
        $problems = [];
        $errors   = [];

        $websites = Website::where('state', WebsiteStateEnum::LIVE)
            ->where('status', true)
            ->whereNotNull('cloudflare_zone_id')
            ->whereNotNull('cloudflare_token')
            ->when($websiteSlug, fn ($query) => $query->where('slug', $websiteSlug))
            ->orderBy('id')
            ->get();

        foreach ($websites as $website) {
            try {
                $urls = $this->cachedRedirects($website);
            } catch (Throwable $e) {
                $errors[] = "$website->slug: ".$e->getMessage();
                continue;
            }

            foreach ($urls as $url) {
                $checked++;
                $problem = $this->check($url);
                if ($problem !== null) {
                    $problems[] = "$website->slug $url: $problem";
                }
            }
        }

        $command?->info("Edge redirects checked: $checked, problems: ".count($problems));
        foreach ([...$problems, ...$errors] as $line) {
            $command?->warn($line);
        }

        if ($problems) {
            $this->alert($problems, $command);
        }

        return ['checked' => $checked, 'problems' => $problems, 'errors' => $errors];
    }

    /**
     * @return array<int, string>
     */
    public function cachedRedirects(Website $website): array
    {
        $query = sprintf(
            '{ viewer { zones(filter: {zoneTag: "%s"}) { redirects: httpRequestsAdaptiveGroups(limit: %d, orderBy: [count_DESC], filter: {datetime_geq: "%s", requestSource: "eyeball", edgeResponseStatus: 301, cacheStatus: "hit", clientRequestHTTPMethodName: "GET"}) { count dimensions { clientRequestHTTPHost clientRequestPath } } } } }',
            $website->cloudflare_zone_id,
            self::PATHS_PER_WEBSITE,
            now()->subHour()->utc()->format('Y-m-d\TH:i:s\Z')
        );

        $response = Http::withToken(decrypt($website->cloudflare_token))
            ->timeout(30)
            ->post('https://api.cloudflare.com/client/v4/graphql', ['query' => $query]);

        if ($response->failed() || $response->json('errors')) {
            throw new RuntimeException(collect($response->json('errors') ?: [])->pluck('message')->implode('; ') ?: 'HTTP '.$response->status());
        }

        return collect($response->json('data.viewer.zones.0.redirects') ?? [])
            ->map(fn (array $group) => 'https://'.$group['dimensions']['clientRequestHTTPHost'].$group['dimensions']['clientRequestPath'])
            ->values()
            ->all();
    }

    public function check(string $url): ?string
    {
        $edge   = $this->location($url, self::BROWSER_AGENT);
        $origin = $this->location($url, self::ORIGIN_AGENT);

        if ($edge !== $origin) {
            return 'Cloudflare sends to '.($edge ?? 'no redirect').', our servers to '.($origin ?? 'no redirect');
        }

        $seen    = [$url];
        $current = $url;
        for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
            $response = $this->fetch($current, self::BROWSER_AGENT);
            if (!$response->redirect()) {
                if ($response->status() === 200 || ($response->status() === 404 && $this->onlyHostChanged($url, $current))) {
                    return null;
                }

                return 'ends on HTTP '.$response->status().' at '.$current;
            }
            $current = $this->absolute($current, (string) $response->header('Location'));
            if (in_array($current, $seen, true)) {
                return 'redirect loop: '.implode(' -> ', [...$seen, $current]);
            }
            $seen[] = $current;
        }

        return 'more than '.self::MAX_HOPS.' redirects: '.implode(' -> ', $seen);
    }

    /**
     * An apex to www or http to https redirect of a path that does not exist (bots asking for
     * favicon.ico or old theme files) correctly ends on a 404: nothing is wrong with the redirect.
     */
    protected function onlyHostChanged(string $from, string $to): bool
    {
        $pathAndQuery = fn (string $url) => (parse_url($url, PHP_URL_PATH) ?? '/').'?'.parse_url($url, PHP_URL_QUERY);

        return $pathAndQuery($from) === $pathAndQuery($to);
    }

    protected function location(string $url, string $userAgent): ?string
    {
        $response = $this->fetch($url, $userAgent);

        return $response->redirect() ? $this->absolute($url, (string) $response->header('Location')) : null;
    }

    protected function fetch(string $url, string $userAgent): Response
    {
        return Http::withUserAgent($userAgent)->withoutRedirecting()->timeout(20)->get($url);
    }

    protected function absolute(string $from, string $location): string
    {
        if (str_starts_with($location, '/')) {
            $parts = parse_url($from);

            return $parts['scheme'].'://'.$parts['host'].$location;
        }

        return $location;
    }

    /**
     * @param array<int, string> $problems
     */
    protected function alert(array $problems, ?Command $command): void
    {
        $webhookUrl = config('services.discord.webhook_url');
        if (!$webhookUrl) {
            $command?->error('Discord webhook URL is not configured');

            return;
        }

        try {
            Http::post($webhookUrl, [
                'content' => "↪️ **Storefront redirects wrong at the Cloudflare edge** ↪️   <@&1164019425154969600>\n\n"
                    .implode("\n", array_map(fn (string $problem) => "- $problem", array_slice($problems, 0, 15)))
                    ."\n\nClear a site's edge copy now: `php artisan cloudflare:purge-host <slug>`",
            ]);
        } catch (Throwable $e) {
            $command?->error('Failed to send Discord notification: '.$e->getMessage());
        }
    }

    public function asCommand(Command $command): int
    {
        $result = $this->handle($command->option('website'), $command);

        return $result['errors'] ? Command::FAILURE : Command::SUCCESS;
    }
}
