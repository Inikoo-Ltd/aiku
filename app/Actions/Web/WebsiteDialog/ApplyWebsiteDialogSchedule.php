<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Website\BreakWebsiteIrisCache;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStateEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStatusEnum;
use App\Models\Web\WebsiteDialog;
use Lorisleiva\Actions\Concerns\AsAction;

class ApplyWebsiteDialogSchedule
{
    use AsAction;
    use WithActionUpdate;

    /**
     * Puts a dialog in the status its own dates call for at the moment the job runs, so a job left
     * over from an earlier publish settles on the dates stored now. Dialogs held down by another
     * one are left to ResumeSupersededWebsiteDialog.
     */
    public function handle(WebsiteDialog $websiteDialog): void
    {
        if ($websiteDialog->state === WebsiteDialogStateEnum::IN_PROCESS || $websiteDialog->paused_by_website_dialog_id) {
            return;
        }

        $status = ($websiteDialog->live_at?->isFuture() || $websiteDialog->schedule_finish_at?->isPast())
            ? WebsiteDialogStatusEnum::INACTIVE
            : WebsiteDialogStatusEnum::ACTIVE;

        if ($websiteDialog->status === $status) {
            return;
        }

        $this->update($websiteDialog, ['status' => $status]);

        BreakWebsiteIrisCache::run($websiteDialog->website);
    }
}
