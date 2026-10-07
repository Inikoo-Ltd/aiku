<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Website\BreakWebsiteIrisCache;
use App\Actions\Web\WebsiteHydrateWebsiteDialogs;
use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteWebsiteDialog extends OrgAction
{
    use WithWebsiteDialogScope;
    use WithWebEditAuthorisation;

    private Website $website;

    public function handle(WebsiteDialog $websiteDialog): void
    {
        $website = $websiteDialog->website;

        ReleasePausedWebsiteDialogs::run($websiteDialog);
        $websiteDialog->delete();

        BreakWebsiteIrisCache::run($website);
        WebsiteHydrateWebsiteDialogs::dispatch($website->id)->delay(2);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.web.website_dialogs.index', [
            'organisation' => $this->website->organisation->slug,
            'shop'         => $this->website->shop->slug,
            'website'      => $this->website->slug,
        ]);
    }

    public function asController(Shop $shop, Website $website, WebsiteDialog $websiteDialog, ActionRequest $request): void
    {
        $this->ensureWebsiteDialogScope($shop, $website, $websiteDialog);
        $this->website = $website;
        $this->initialisationFromShop($shop, $request);

        $this->handle($websiteDialog);
    }
}
