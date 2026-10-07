<?php

namespace App\Actions\Web\WebsiteDialog\UI;

use App\Enums\Web\WebsiteDialog\WebsiteDialogStatusEnum;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetIrisWebsiteDialogs
{
    use AsAction;

    /**
     * The published dialogs the storefront may pop up, newest first, with the schedule fields the
     * client uses to decide which one is visible at a given moment. Drafts never reach it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(Website $website): array
    {
        $cacheKey = "irisData:website:$website->id:dialogs";

        $websiteDialogs = Cache::get($cacheKey);
        if ($websiteDialogs !== null) {
            return $websiteDialogs;
        }

        $websiteDialogs = $website->websiteDialogs()
            ->whereNotNull('published_layout')
            ->where(
                fn ($query) => $query
                    ->where('status', WebsiteDialogStatusEnum::ACTIVE)
                    ->orWhere(
                        fn ($query) => $query
                            ->whereNotNull('paused_by_website_dialog_id')
                            ->whereNotNull('paused_until')
                    )
            )
            ->where(
                fn ($query) => $query
                    ->whereNull('schedule_finish_at')
                    ->orWhere('schedule_finish_at', '>', now())
            )
            ->orderByRaw('coalesce(ready_at, live_at, created_at) desc')
            ->get()
            ->map(fn (WebsiteDialog $websiteDialog) => $this->toIrisData($websiteDialog))
            ->all();

        Cache::put($cacheKey, $websiteDialogs, $this->getCacheTtl($website));

        return $websiteDialogs;
    }

    /**
     * @return array<string, mixed>
     */
    public function toIrisData(WebsiteDialog $websiteDialog): array
    {
        $layout   = $websiteDialog->published_layout ?? [];
        $settings = Arr::get($layout, 'settings') ?? [];

        return [
            'ulid'                 => $websiteDialog->ulid,
            'version'              => $websiteDialog->published_checksum,
            'template_code'        => Arr::get($layout, 'template_code'),
            'component'            => Arr::get($layout, 'component'),
            'fields'               => Arr::get($layout, 'fields') ?? [],
            'container_properties' => Arr::get($layout, 'container_properties') ?? [],
            'settings'             => [
                'trigger'           => $websiteDialog->getTrigger(),
                'target_users'      => Arr::get($settings, 'target_users', ['auth_state' => 'all']),
                'display_frequency' => $websiteDialog->getDisplayFrequency(),
                'delay_seconds'     => (int)Arr::get($settings, 'delay_seconds', 0),
            ],
            ...$websiteDialog->extractTargetPages($settings),
            'schedule_at'          => $websiteDialog->schedule_at,
            'schedule_finish_at'   => $websiteDialog->schedule_finish_at,
            'resumes_at'           => $websiteDialog->status === WebsiteDialogStatusEnum::ACTIVE ? null : $websiteDialog->paused_until,
        ];
    }

    /**
     * Seconds until the next moment a dialog starts, finishes or comes back, so a scheduled change
     * shows up on time instead of whenever the cache happens to expire.
     */
    public function getCacheTtl(Website $website): int
    {
        $now = now();

        $next = $website->websiteDialogs()
            ->where(
                fn ($query) => $query
                    ->where('schedule_at', '>', $now)
                    ->orWhere('schedule_finish_at', '>', $now)
                    ->orWhere('paused_until', '>', $now)
            )
            ->get(['schedule_at', 'schedule_finish_at', 'paused_until'])
            ->flatMap(fn (WebsiteDialog $websiteDialog) => [$websiteDialog->schedule_at, $websiteDialog->schedule_finish_at, $websiteDialog->paused_until])
            ->filter(fn ($moment) => $moment && $moment->gt($now))
            ->min();

        if (!$next) {
            return 7200;
        }

        return max(60, min(7200, (int)$now->diffInSeconds($next, false)));
    }
}
