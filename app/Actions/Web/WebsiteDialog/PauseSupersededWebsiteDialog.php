<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Website\BreakWebsiteIrisCache;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStatusEnum;
use App\Models\Web\WebsiteDialog;
use Lorisleiva\Actions\Concerns\AsAction;

class PauseSupersededWebsiteDialog
{
    use AsAction;
    use WithActionUpdate;

    /**
     * Takes a dialog off the website at the moment the one superseding it goes live. The window is
     * read from the superseding dialog so a job left over from an earlier publish does nothing.
     */
    public function handle(WebsiteDialog $websiteDialog, int $pausedByWebsiteDialogId): void
    {
        $pausedBy = WebsiteDialog::find($pausedByWebsiteDialogId);

        if (!$pausedBy || ($websiteDialog->status !== WebsiteDialogStatusEnum::ACTIVE && !$websiteDialog->isWaitingForStart())) {
            return;
        }

        if ($pausedBy->live_at?->isFuture() || $pausedBy->schedule_finish_at?->isPast()) {
            return;
        }

        $this->update($websiteDialog, [
            'status'                      => WebsiteDialogStatusEnum::INACTIVE,
            'paused_by_website_dialog_id' => $pausedBy->id,
            'paused_until'                => $pausedBy->schedule_finish_at,
        ]);

        BreakWebsiteIrisCache::run($websiteDialog->website);
    }
}
