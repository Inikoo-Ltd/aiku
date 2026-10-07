<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Web\WebsiteDialogResource;
use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class ResetWebsiteDialog extends OrgAction
{
    use WithWebsiteDialogScope;
    use WithActionUpdate;
    use WithWebEditAuthorisation;

    /**
     * Throws away the draft and goes back to what is published.
     */
    public function handle(WebsiteDialog $websiteDialog): WebsiteDialog
    {
        $publishedLayout = $websiteDialog->published_layout;

        if (!$publishedLayout) {
            return $websiteDialog;
        }

        $websiteDialog->unpublishedSnapshot->update(['layout' => $publishedLayout]);

        return $this->update($websiteDialog, [
            'template_code'        => Arr::get($publishedLayout, 'template_code'),
            'component'            => Arr::get($publishedLayout, 'component'),
            'fields'               => Arr::get($publishedLayout, 'fields') ?? [],
            'container_properties' => Arr::get($publishedLayout, 'container_properties') ?? [],
            'settings'             => Arr::get($publishedLayout, 'settings') ?? [],
            'is_dirty'             => false,
        ]);
    }

    public function jsonResponse(WebsiteDialog $websiteDialog): WebsiteDialogResource
    {
        return WebsiteDialogResource::make($websiteDialog->refresh());
    }

    public function asController(Shop $shop, Website $website, WebsiteDialog $websiteDialog, ActionRequest $request): WebsiteDialog
    {
        $this->ensureWebsiteDialogScope($shop, $website, $websiteDialog);
        $this->initialisationFromShop($shop, $request);

        return $this->handle($websiteDialog);
    }
}
