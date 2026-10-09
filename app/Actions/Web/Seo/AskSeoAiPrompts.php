<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\SeoAiPrompt;
use App\Services\DataForSeo\DataForSeoClient;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Queues one `AskSeoAiPrompt` job for every active prompt that has not been answered for a week, so
 * the live calls run side by side on the workers. A prompt stays marked as queued until its answer
 * is stored; one still marked after `STALE_HOURS` is queued again.
 */
class AskSeoAiPrompts
{
    use AsAction;

    public const int DAYS_BETWEEN_RUNS = 7;

    private const int STALE_HOURS = 6;

    public string $commandSignature = 'seo:ask_ai_prompts';

    public string $commandDescription = 'Ask ChatGPT the AI visibility prompts that are due, through DataForSEO';

    public string $jobQueue = 'long-low-priority';

    public int $jobTries = 1;

    public function handle(?Shop $shop = null, ?int $limit = null): int
    {
        if (!DataForSeoClient::make()) {
            return 0;
        }

        $prompts = SeoAiPrompt::query()
            ->where('is_active', true)
            ->whereHas('shop.website', fn (Builder $query) => $query->where('state', WebsiteStateEnum::LIVE))
            ->when($shop, fn (Builder $query) => $query->where('shop_id', $shop->id))
            ->where(fn (Builder $query) => $query
                ->whereNull('queued_at')
                ->orWhere('queued_at', '<', now()->subHours(self::STALE_HOURS)))
            ->where(fn (Builder $query) => $query
                ->whereNull('last_run_at')
                ->orWhere('last_run_at', '<=', today()->subDays(self::DAYS_BETWEEN_RUNS)))
            ->orderBy('id')
            ->when($limit, fn (Builder $query) => $query->limit($limit))
            ->get();

        foreach ($prompts as $prompt) {
            $prompt->update(['queued_at' => now()]);
            AskSeoAiPrompt::dispatch($prompt);
        }

        return $prompts->count();
    }

    public function asCommand(Command $command): int
    {
        $command->line($this->handle().' prompts queued');

        return 0;
    }
}
