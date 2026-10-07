<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Website\BreakWebsiteIrisCache;
use App\Actions\Web\WebsiteHydrateWebsiteDialogs;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStateEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStatusEnum;
use App\Http\Resources\Web\WebsiteDialogResource;
use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class ToggleWebsiteDialog extends OrgAction
{
    use WithWebsiteDialogScope;
    use WithActionUpdate;
    use WithWebEditAuthorisation;

    public function handle(WebsiteDialog $websiteDialog, WebsiteDialogStatusEnum $status): WebsiteDialog
    {
        if ($status === WebsiteDialogStatusEnum::ACTIVE && $websiteDialog->state === WebsiteDialogStateEnum::IN_PROCESS) {
            throw ValidationException::withMessages([
                'status' => __('Publish the dialog before turning it on.')
            ]);
        }

        $this->update($websiteDialog, [
            'status'                      => $status,
            'paused_by_website_dialog_id' => null,
            'paused_until'                => null,
        ]);

        BreakWebsiteIrisCache::run($websiteDialog->website);
        WebsiteHydrateWebsiteDialogs::dispatch($websiteDialog->website_id)->delay(2);

        return $websiteDialog;
    }

    public function jsonResponse(WebsiteDialog $websiteDialog): WebsiteDialogResource
    {
        return WebsiteDialogResource::make($websiteDialog->refresh());
    }

    public function asController(Shop $shop, Website $website, WebsiteDialog $websiteDialog, ActionRequest $request): WebsiteDialog
    {
        $this->ensureWebsiteDialogScope($shop, $website, $websiteDialog);
        $this->initialisationFromShop($shop, $request);

        $status = $websiteDialog->status === WebsiteDialogStatusEnum::ACTIVE
            ? WebsiteDialogStatusEnum::INACTIVE
            : WebsiteDialogStatusEnum::ACTIVE;

        return $this->handle($websiteDialog, $status);
    }
}
