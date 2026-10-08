<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Web\WebsiteDialog;
use Lorisleiva\Actions\Concerns\AsAction;

class ReleasePausedWebsiteDialogs
{
    use AsAction;
    use WithActionUpdate;

    /**
     * Gives back the website to every dialog this one had paused, each in the status its own dates
     * call for.
     */
    public function handle(WebsiteDialog $websiteDialog): void
    {
        foreach ($websiteDialog->pausedWebsiteDialogs as $paused) {
            $this->update($paused, [
                'status'                      => $paused->statusForOwnDates(),
                'paused_by_website_dialog_id' => null,
                'paused_until'                => null,
            ]);
        }
    }
}
