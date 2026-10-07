<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStatusEnum;
use App\Models\Web\WebsiteDialog;
use Lorisleiva\Actions\Concerns\AsAction;

class ReleasePausedWebsiteDialogs
{
    use AsAction;
    use WithActionUpdate;

    /**
     * Gives back the website to every dialog this one had paused.
     */
    public function handle(WebsiteDialog $websiteDialog): void
    {
        foreach ($websiteDialog->pausedWebsiteDialogs as $paused) {
            $this->update($paused, [
                'status'                      => WebsiteDialogStatusEnum::ACTIVE,
                'paused_by_website_dialog_id' => null,
                'paused_until'                => null,
            ]);
        }
    }
}
