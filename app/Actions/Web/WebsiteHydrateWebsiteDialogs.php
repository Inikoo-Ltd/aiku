<?php

namespace App\Actions\Web;

use App\Enums\Web\WebsiteDialog\WebsiteDialogStatusEnum;
use App\Models\Web\Website;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsiteHydrateWebsiteDialogs implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(?int $websiteId): string
    {
        return $websiteId ?? 'empty';
    }

    public function handle(?int $websiteId): void
    {
        if (!$websiteId) {
            return;
        }

        $website = Website::find($websiteId);

        if (!$website) {
            return;
        }

        $website->webStats->update([
            'number_website_dialogs'          => $website->websiteDialogs()->count(),
            'number_active_website_dialogs'   => $website->websiteDialogs()->where('status', WebsiteDialogStatusEnum::ACTIVE)->count(),
            'number_inactive_website_dialogs' => $website->websiteDialogs()->where('status', WebsiteDialogStatusEnum::INACTIVE)->count(),
        ]);
    }
}
