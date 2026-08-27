<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Website\BreakWebsiteIrisCache;
use App\Enums\Announcement\AnnouncementStateEnum;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Models\Web\Announcement;
use Lorisleiva\Actions\Concerns\AsAction;

class ApplyAnnouncementSchedule
{
    use AsAction;
    use WithActionUpdate;

    /**
     * Puts an announcement in the status its own dates call for at the moment the job runs. Every
     * publish queues one of these at each end of its window, so a job left over from an earlier
     * publish settles on the dates that are stored now instead of the ones it was queued with.
     * Announcements held down by another one are left to ResumeSupersededAnnouncement.
     */
    public function handle(Announcement $announcement): void
    {
        if ($announcement->state == AnnouncementStateEnum::IN_PROCESS or $announcement->paused_by_announcement_id) {
            return;
        }

        $status = $this->scheduledStatus($announcement);

        if ($announcement->status == $status) {
            return;
        }

        $this->update($announcement, ['status' => $status]);

        BreakWebsiteIrisCache::run($announcement->website);
    }

    private function scheduledStatus(Announcement $announcement): AnnouncementStatusEnum
    {
        if ($announcement->live_at?->isFuture() or $announcement->schedule_finish_at?->isPast()) {
            return AnnouncementStatusEnum::INACTIVE;
        }

        return AnnouncementStatusEnum::ACTIVE;
    }
}
