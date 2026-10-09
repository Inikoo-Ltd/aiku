<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Web\SeoTrackedKeyword;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Collects the Google checks `PostSerpTasks` queued once DataForSEO lists them as ready. Reading a
 * result is free. Other environments sharing the DataForSEO account tag their tasks differently,
 * and their tasks are left for them to collect.
 */
class CollectSerpTasks
{
    use AsAction;

    private const int READY_LIMIT = 1000;

    public string $commandSignature = 'seo:collect_serp_tasks';

    public string $commandDescription = 'Store the Google checks of the tracked keywords that DataForSEO has finished';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 3600;

    public int $jobTries = 1;

    /**
     * @throws DataForSeoException
     */
    public function handle(): int
    {
        $client = DataForSeoClient::make();

        if (!$client || !SeoTrackedKeyword::whereNotNull('pending_task_id')->exists()) {
            return 0;
        }

        $storeSerpResult = StoreSerpResult::make();
        $collected       = 0;

        do {
            $readyTasks = collect($client->get('serp/google/organic/tasks_ready'));

            $trackedKeywords = SeoTrackedKeyword::query()
                ->whereIn('pending_task_id', $readyTasks
                    ->filter(fn (array $task) => Str::startsWith((string) Arr::get($task, 'tag'), PostSerpTasks::tagPrefix()))
                    ->pluck('id')
                    ->filter()
                    ->all())
                ->with(['shop.website', 'shop.seoCompetitors'])
                ->get();

            foreach ($trackedKeywords as $trackedKeyword) {
                try {
                    $result = Arr::first($client->get("serp/google/organic/task_get/advanced/$trackedKeyword->pending_task_id", $trackedKeyword->shop->website));
                } catch (DataForSeoException) {
                    $trackedKeyword->update(['pending_task_id' => null, 'pending_task_posted_at' => null]);

                    continue;
                }

                if ($result) {
                    $storeSerpResult->handle($trackedKeyword, $result);
                    $collected++;
                }
            }
        } while ($trackedKeywords->isNotEmpty() && $readyTasks->count() >= self::READY_LIMIT);

        if ($collected) {
            NotifySeoRankingAlerts::dispatch();
        }

        return $collected;
    }

    public function asCommand(Command $command): int
    {
        try {
            $command->line($this->handle().' checks stored');
        } catch (DataForSeoException $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
