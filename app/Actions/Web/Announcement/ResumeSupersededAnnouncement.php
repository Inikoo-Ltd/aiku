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

class ResumeSupersededAnnouncement
{
    use AsAction;
    use WithActionUpdate;

    /**
     * Brings back an announcement that was paused by another one, unless somebody has
     * touched its status in the meantime, which clears the pause marks, or the pause has
     * since been extended, which leaves this job over from an earlier publish.
     */
    public function handle(Announcement $announcement, int $pausedByAnnouncementId): void
    {
        if ($announcement->paused_by_announcement_id !== $pausedByAnnouncementId) {
            return;
        }

        if ($announcement->paused_until?->isFuture()) {
            return;
        }

        $this->update($announcement, [
            'status'                    => AnnouncementStatusEnum::ACTIVE,
            'paused_by_announcement_id' => null,
            'paused_until'              => null,
        ]);

        BreakWebsiteIrisCache::run($announcement->website);
    }
}
