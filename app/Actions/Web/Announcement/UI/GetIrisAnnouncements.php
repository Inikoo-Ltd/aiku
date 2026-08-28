<?php

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Models\Web\Announcement;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetIrisAnnouncements extends OrgAction
{
    use AsAction;

    /**
     * The exact announcements payload the storefront receives: every candidate Iris may show,
     * newest first, carrying the schedule fields its client uses to decide what is visible at a
     * given moment. Shared by the Iris middleware and the back-office simulator so both always
     * agree on what the website displays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(Website $website): array
    {
        $cacheKey = "irisData:website:$website->id:announcements";

        $announcements = Cache::get($cacheKey);
        if ($announcements !== null) {
            return $announcements;
        }

        $announcements = [];

        $candidates = $website->announcements()
            ->where(
                fn ($query) => $query
                    ->where('status', AnnouncementStatusEnum::ACTIVE)
                    ->orWhere(
                        fn ($query) => $query
                            ->whereNotNull('paused_by_announcement_id')
                            ->whereNotNull('paused_until')
                    )
            )
            ->orderByRaw('coalesce(ready_at, live_at, created_at) desc')
            ->get();

        /** @var Announcement $announcement */
        foreach ($candidates as $announcement) {
            $extractedSettings = $announcement->extractSettings($announcement->settings);

            $announcements[] = [
                'ulid'                 => $announcement->ulid,
                'name'                 => $announcement->name,
                'show_pages'           => $extractedSettings['show_pages'],
                'hide_pages'           => $extractedSettings['hide_pages'],
                'container_properties' => $announcement->container_properties,
                'fields'               => $announcement->fields,
                'schedule_at'          => $announcement->schedule_at,
                'schedule_finish_at'   => $announcement->schedule_finish_at,
                'resumes_at'           => $announcement->status === AnnouncementStatusEnum::ACTIVE
                                            ? null
                                            : $announcement->paused_until,
                'settings'             => $announcement->settings,
                'template_code'        => $announcement->template_code,
            ];
        }

        Cache::put($cacheKey, $announcements, $this->getCacheTtl($website));

        return $announcements;
    }

    /**
     * Seconds until the next moment an announcement starts, finishes or comes back, so a
     * scheduled change shows up on time instead of whenever the cache happens to expire.
     */
    public function getCacheTtl(Website $website): int
    {
        $now = now();

        $next = $website->announcements()
            ->where(
                fn ($query) => $query
                    ->where('schedule_at', '>', $now)
                    ->orWhere('schedule_finish_at', '>', $now)
                    ->orWhere('paused_until', '>', $now)
            )
            ->get(['schedule_at', 'schedule_finish_at', 'paused_until'])
            ->flatMap(fn (Announcement $announcement) => [$announcement->schedule_at, $announcement->schedule_finish_at, $announcement->paused_until])
            ->filter(fn ($moment) => $moment and $moment->gt($now))
            ->min();

        if (!$next) {
            return 7200;
        }

        return max(60, min(7200, (int)$now->diffInSeconds($next, false)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function asController(Website $website): array
    {
        return $this->handle($website);
    }
}
