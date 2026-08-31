<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Website\BreakWebsiteIrisCache;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Models\Web\Announcement;
use Lorisleiva\Actions\Concerns\AsAction;

class PauseSupersededAnnouncement
{
    use AsAction;
    use WithActionUpdate;

    /**
     * Takes an announcement off its spot at the moment the one superseding it goes live. The window
     * is read from the superseding announcement so that a job left over from an earlier publish,
     * whose dates have moved since, does nothing.
     */
    public function handle(Announcement $announcement, int $pausedByAnnouncementId): void
    {
        $pausedBy = Announcement::find($pausedByAnnouncementId);

        if (!$pausedBy or $announcement->status !== AnnouncementStatusEnum::ACTIVE) {
            return;
        }

        if ($pausedBy->live_at?->isFuture() or $pausedBy->schedule_finish_at?->isPast()) {
            return;
        }

        $this->update($announcement, [
            'status'                    => AnnouncementStatusEnum::INACTIVE,
            'paused_by_announcement_id' => $pausedBy->id,
            'paused_until'              => $pausedBy->schedule_finish_at,
        ]);

        BreakWebsiteIrisCache::run($announcement->website);
    }
}
