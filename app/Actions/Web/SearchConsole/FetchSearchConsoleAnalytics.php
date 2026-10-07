<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\SearchConsole;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\SearchConsolePageDay;
use App\Models\Web\SearchConsolePageQuery;
use App\Models\Web\SearchConsoleWebsiteDay;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\SearchConsole\SearchConsoleClient;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Carbon\CarbonPeriod;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class FetchSearchConsoleAnalytics implements ShouldBeUnique
{
    use AsAction;

    public const int HISTORY_MONTHS = 16;

    public const int REFETCH_DAYS = 3;

    private const int UPSERT_CHUNK = 1000;

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 10800;

    public int $jobTries = 1;

    public function getJobUniqueId(Website $website, ?string $from = null, ?string $to = null): string
    {
        return "$website->id:$from:$to";
    }

    public function handle(Website $website, ?string $from = null, ?string $to = null): int
    {
        $client = SearchConsoleClient::forWebsite($website);

        if (!$client) {
            return 0;
        }

        $toDate   = Carbon::parse($to ?? now()->subDay())->toDateString();
        $fromDate = Carbon::parse($from ?? $this->defaultFromDate($website))->toDateString();

        if ($fromDate > $toDate) {
            return 0;
        }

        $webpageIdsByPath = $this->webpageIdsByPath($website);
        $daysWithData     = 0;

        foreach (CarbonPeriod::create($fromDate, $toDate) as $day) {
            $date = $day->toDateString();

            if (!$this->storeWebsiteDays($client, $website, $date)) {
                continue;
            }

            $this->storePageDays($client, $website, $date, $webpageIdsByPath);
            $this->storePageQueries($client, $website, $date, $webpageIdsByPath);
            $daysWithData++;
        }

        return $daysWithData;
    }

    private function defaultFromDate(Website $website): string
    {
        $lastStoredDate = SearchConsoleWebsiteDay::where('website_id', $website->id)->max('date');

        if ($lastStoredDate) {
            return Carbon::parse($lastStoredDate)->subDays(self::REFETCH_DAYS)->toDateString();
        }

        return now()->subMonths(self::HISTORY_MONTHS)->toDateString();
    }

    private function storeWebsiteDays(SearchConsoleClient $client, Website $website, string $date): bool
    {
        $now  = now();
        $rows = array_map(fn (array $row) => [
            'website_id'  => $website->id,
            'date'        => $date,
            'country'     => $row['keys'][1],
            'device'      => strtolower($row['keys'][2]),
            'clicks'      => $row['clicks'],
            'impressions' => $row['impressions'],
            'position'    => $row['position'],
            'created_at'  => $now,
            'updated_at'  => $now,
        ], $client->rowsForDay(['date', 'country', 'device'], $date));

        $this->upsert(SearchConsoleWebsiteDay::class, $rows, ['website_id', 'date', 'country', 'device'], ['clicks', 'impressions', 'position', 'updated_at']);

        return $rows !== [];
    }

    private function storePageDays(SearchConsoleClient $client, Website $website, string $date, array $webpageIdsByPath): void
    {
        $now  = now();
        $rows = array_map(fn (array $row) => [
            'website_id'    => $website->id,
            'webpage_id'    => $this->matchWebpageId($row['keys'][1], $webpageIdsByPath),
            'date'          => $date,
            'page_url'      => $row['keys'][1],
            'page_url_hash' => md5($row['keys'][1]),
            'clicks'        => $row['clicks'],
            'impressions'   => $row['impressions'],
            'position'      => $row['position'],
            'created_at'    => $now,
            'updated_at'    => $now,
        ], $client->rowsForDay(['date', 'page'], $date));

        $this->upsert(SearchConsolePageDay::class, $rows, ['website_id', 'date', 'page_url_hash'], ['webpage_id', 'clicks', 'impressions', 'position', 'updated_at']);
    }

    private function storePageQueries(SearchConsoleClient $client, Website $website, string $date, array $webpageIdsByPath): void
    {
        $now  = now();
        $rows = array_map(fn (array $row) => [
            'website_id'      => $website->id,
            'webpage_id'      => $this->matchWebpageId($row['keys'][1], $webpageIdsByPath),
            'date'            => $date,
            'page_url'        => $row['keys'][1],
            'query'           => $row['keys'][2],
            'page_query_hash' => md5($row['keys'][1]."\n".$row['keys'][2]),
            'clicks'          => $row['clicks'],
            'impressions'     => $row['impressions'],
            'position'        => $row['position'],
            'created_at'      => $now,
            'updated_at'      => $now,
        ], $client->rowsForDay(['date', 'page', 'query'], $date));

        $this->upsert(SearchConsolePageQuery::class, $rows, ['website_id', 'date', 'page_query_hash'], ['webpage_id', 'clicks', 'impressions', 'position', 'updated_at']);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function upsert(string $model, array $rows, array $uniqueBy, array $update): void
    {
        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            $model::query()->upsert($chunk, $uniqueBy, $update);
        }
    }

    /**
     * @return array<string, int>
     */
    private function webpageIdsByPath(Website $website): array
    {
        $webpageIdsByPath = [];

        $webpages = Webpage::where('website_id', $website->id)
            ->orderByRaw('CASE WHEN state = ? THEN 1 ELSE 0 END', [WebpageStateEnum::LIVE->value])
            ->get(['id', 'url', 'canonical_url']);

        foreach ($webpages as $webpage) {
            $webpageIdsByPath[$this->normalisePath('/'.ltrim((string) $webpage->url, '/'))] = $webpage->id;

            if ($webpage->canonical_url) {
                $webpageIdsByPath[$this->normalisePath(parse_url($webpage->canonical_url, PHP_URL_PATH) ?: '/')] = $webpage->id;
            }
        }

        return $webpageIdsByPath;
    }

    private function matchWebpageId(string $pageUrl, array $webpageIdsByPath): ?int
    {
        return $webpageIdsByPath[$this->normalisePath(parse_url($pageUrl, PHP_URL_PATH) ?: '/')] ?? null;
    }

    private function normalisePath(string $path): string
    {
        $path = rawurldecode($path);

        return $path === '/' ? $path : rtrim($path, '/');
    }

    public function getCommandSignature(): string
    {
        return 'search_console:fetch {website? : Website slug} {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';
    }

    public function asCommand(Command $command): int
    {
        $websites = Website::query()
            ->when($command->argument('website'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        $from = $command->option('from');
        $to   = $command->option('to');

        foreach ($websites as $website) {
            if ($command->option('async')) {
                self::dispatch($website, $from, $to);

                continue;
            }

            try {
                $daysWithData = $this->handle($website, $from, $to);
                $command->line("$website->slug: $daysWithData days with data");
            } catch (Throwable $e) {
                $command->error("$website->slug: {$e->getMessage()}");
            }
        }

        return 0;
    }
}
