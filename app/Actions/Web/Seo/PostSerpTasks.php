<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Enums\Web\Seo\SeoKeywordDeviceEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\SeoTrackedKeyword;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Queues a Google check for every active tracked keyword that is due, through DataForSEO's standard
 * queue (the cheapest, results within minutes to an hour). `CollectSerpTasks` picks the results up.
 * Daily keywords are read to the top 20 and the others to the top 30, because DataForSEO bills per
 * page of 10 results. Monthly keywords are due 30 days after their last check. Keywords set to On
 * request are never due: `requested()` checks them when someone asks.
 */
class PostSerpTasks
{
    use AsAction;

    public const int WEEKLY_DEPTH = 30;

    public const int DAILY_DEPTH = 20;

    public const float COST_PER_PAGE = 0.0006;

    private const int TASKS_PER_REQUEST = 100;

    private const int STALE_TASK_HOURS = 72;

    public string $commandSignature = 'seo:post_serp_tasks';

    public string $commandDescription = 'Queue the Google checks of the tracked keywords that are due';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 3600;

    public int $jobTries = 1;

    public static function depth(SeoKeywordFrequencyEnum $frequency): int
    {
        return $frequency === SeoKeywordFrequencyEnum::DAILY ? self::DAILY_DEPTH : self::WEEKLY_DEPTH;
    }

    public static function tagPrefix(): string
    {
        return 'aiku:'.substr(md5((string) config('app.url')), 0, 8).':';
    }

    /**
     * @throws DataForSeoException
     */
    public function handle(?Shop $shop = null): int
    {
        $client = DataForSeoClient::make();

        if (!$client) {
            return 0;
        }

        $posted = 0;

        $this->dueKeywords()->when($shop, fn (Builder $query) => $query->where('shop_id', $shop->id))->chunkById(self::TASKS_PER_REQUEST, function (Collection $trackedKeywords) use ($client, &$posted) {
            $posted += $this->post($client, $trackedKeywords);
        });

        return $posted;
    }

    /**
     * Queues a check now for the shop's active keywords, or only the given ones, whatever their
     * frequency. A keyword already checked today or still waiting for its result is left out, so
     * pressing twice costs nothing.
     *
     * @param  array<int, int>|null  $trackedKeywordIds
     * @throws DataForSeoException
     */
    /**
     * Queues a check for the shop's requestable keywords and returns how many keywords it covers: a
     * keyword tracked in several countries counts once.
     */
    public function requested(Shop $shop, ?array $trackedKeywordIds = null): int
    {
        $client = DataForSeoClient::make();

        if (!$client) {
            return 0;
        }

        $keywords = [];

        $this->requestableKeywords($shop)
            ->when($trackedKeywordIds !== null, fn (Builder $query) => $query->whereIn('id', $trackedKeywordIds))
            ->chunkById(self::TASKS_PER_REQUEST, function (Collection $trackedKeywords) use ($client, &$keywords) {
                if ($this->post($client, $trackedKeywords) > 0) {
                    $keywords += array_fill_keys($trackedKeywords->pluck('keyword')->all(), true);
                }
            });

        return count($keywords);
    }

    /**
     * How many keywords a check now would cover, counted once whatever their countries and
     * languages, and what DataForSEO bills for every check it queues: one page of 10 results at a
     * time, to each keyword's depth.
     *
     * @return array{keywords: int, cost: float}
     */
    public function requestable(Shop $shop): array
    {
        $checks = $this->requestableKeywords($shop)->get(['keyword', 'frequency']);

        return [
            'keywords' => $checks->pluck('keyword')->unique()->count(),
            'cost'     => self::cost($checks->pluck('frequency')),
        ];
    }

    /**
     * What DataForSEO bills to check keywords of these frequencies: one page of 10 results at a
     * time, to each keyword's depth.
     *
     * @param  iterable<int, SeoKeywordFrequencyEnum>  $frequencies
     */
    public static function cost(iterable $frequencies): float
    {
        return round(collect($frequencies)->sum(fn (SeoKeywordFrequencyEnum $frequency) => self::depth($frequency) / 10 * self::COST_PER_PAGE), 4);
    }

    /**
     * The shop's keywords a check now would queue: active, not checked today and not waiting for a
     * result.
     *
     * @return Builder<SeoTrackedKeyword>
     */
    public function requestableKeywords(Shop $shop): Builder
    {
        return $this->checkableKeywords()
            ->where('shop_id', $shop->id)
            ->where(fn (Builder $query) => $query
                ->whereNull('last_checked_at')
                ->orWhere('last_checked_at', '<', today()));
    }

    /**
     * Active keywords on a tracked device of live websites with no check waiting for its result.
     *
     * @return Builder<SeoTrackedKeyword>
     */
    private function checkableKeywords(): Builder
    {
        return SeoTrackedKeyword::query()
            ->where('is_active', true)
            ->whereIn('device', SeoKeywordDeviceEnum::tracked())
            ->whereHas('shop.website', fn (Builder $query) => $query->where('state', WebsiteStateEnum::LIVE))
            ->where(fn (Builder $query) => $query
                ->whereNull('pending_task_id')
                ->orWhere('pending_task_posted_at', '<', now()->subHours(self::STALE_TASK_HOURS)));
    }

    /**
     * @return Builder<SeoTrackedKeyword>
     */
    private function dueKeywords(): Builder
    {
        return $this->checkableKeywords()
            ->whereIn('frequency', SeoKeywordFrequencyEnum::scheduled())
            ->where(function (Builder $query) {
                $query->whereNull('last_checked_at');

                foreach (SeoKeywordFrequencyEnum::scheduled() as $frequency) {
                    $query->orWhere(fn (Builder $query) => $query
                        ->where('frequency', $frequency)
                        ->where('last_checked_at', '<=', today()->subDays($frequency->daysBetweenChecks())));
                }
            });
    }

    /**
     * @param  Collection<int, SeoTrackedKeyword>  $trackedKeywords
     * @throws DataForSeoException
     */
    private function post(DataForSeoClient $client, Collection $trackedKeywords): int
    {
        $tasks = [];

        foreach ($trackedKeywords as $trackedKeyword) {
            try {
                [$locationCode, $languageCode] = GetKeywordIdeas::make()->location($client, $trackedKeyword->country_code, $trackedKeyword->language_code);
            } catch (ValidationException) {
                continue;
            }

            $tasks[] = [
                'keyword'       => $trackedKeyword->keyword,
                'location_code' => $locationCode,
                'language_code' => $languageCode,
                'device'        => $trackedKeyword->device->value,
                'depth'         => self::depth($trackedKeyword->frequency),
                'tag'           => self::tagPrefix().$trackedKeyword->id,
            ];
        }

        if ($tasks === []) {
            return 0;
        }

        $posted = 0;

        foreach ($client->postTasks('serp/google/organic/task_post', $tasks) as $task) {
            $tag = (string) Arr::get($task, 'data.tag');

            if (!DataForSeoClient::isTaskCreated($task) || !Str::startsWith($tag, self::tagPrefix())) {
                continue;
            }

            SeoTrackedKeyword::where('id', (int) Str::after($tag, self::tagPrefix()))->update([
                'pending_task_id'        => Arr::get($task, 'id'),
                'pending_task_posted_at' => now(),
            ]);

            $posted++;
        }

        return $posted;
    }

    public function asCommand(Command $command): int
    {
        try {
            $command->line($this->handle().' checks queued');
        } catch (DataForSeoException $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
