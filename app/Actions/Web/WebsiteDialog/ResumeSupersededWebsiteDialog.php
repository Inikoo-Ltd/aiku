<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Website\BreakWebsiteIrisCache;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStatusEnum;
use App\Models\Web\WebsiteDialog;
use Lorisleiva\Actions\Concerns\AsAction;

class ResumeSupersededWebsiteDialog
{
    use AsAction;
    use WithActionUpdate;

    /**
     * Brings back a dialog paused by another one, unless its status was touched in the meantime,
     * which clears the pause marks, or the pause has since been extended.
     */
    public function handle(WebsiteDialog $websiteDialog, int $pausedByWebsiteDialogId): void
    {
        if ($websiteDialog->paused_by_website_dialog_id !== $pausedByWebsiteDialogId) {
            return;
        }

        if ($websiteDialog->paused_until?->isFuture()) {
            return;
        }

        $this->update($websiteDialog, [
            'status'                      => WebsiteDialogStatusEnum::ACTIVE,
            'paused_by_website_dialog_id' => null,
            'paused_until'                => null,
        ]);

        BreakWebsiteIrisCache::run($websiteDialog->website);
    }
}
