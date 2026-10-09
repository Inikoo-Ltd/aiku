<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

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
 * Weekly keywords are read to the top 30 and daily ones to the top 20, because DataForSEO bills per
 * page of 10 results.
 */
class PostSerpTasks
{
    use AsAction;

    public const int WEEKLY_DEPTH = 30;

    public const int DAILY_DEPTH = 20;

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
     * @return Builder<SeoTrackedKeyword>
     */
    private function dueKeywords(): Builder
    {
        return SeoTrackedKeyword::query()
            ->where('is_active', true)
            ->whereHas('shop.website', fn (Builder $query) => $query->where('state', WebsiteStateEnum::LIVE))
            ->where(fn (Builder $query) => $query
                ->whereNull('pending_task_id')
                ->orWhere('pending_task_posted_at', '<', now()->subHours(self::STALE_TASK_HOURS)))
            ->where(fn (Builder $query) => $query
                ->whereNull('last_checked_at')
                ->orWhere(fn (Builder $query) => $query
                    ->where('frequency', SeoKeywordFrequencyEnum::DAILY)
                    ->where('last_checked_at', '<', today()))
                ->orWhere(fn (Builder $query) => $query
                    ->where('frequency', SeoKeywordFrequencyEnum::WEEKLY)
                    ->where('last_checked_at', '<', today()->subDays(6))));
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
