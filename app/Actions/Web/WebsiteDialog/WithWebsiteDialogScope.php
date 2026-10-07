<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;

trait WithWebsiteDialogScope
{
    /**
     * Route parameters are bound without scoping, so make sure the dialog really belongs to the
     * website and shop the permissions are checked against.
     */
    protected function ensureWebsiteDialogScope(?Shop $shop, Website $website, ?WebsiteDialog $websiteDialog = null): void
    {
        if ($shop && $website->shop_id !== $shop->id) {
            abort(404);
        }

        if ($websiteDialog && $websiteDialog->website_id !== $website->id) {
            abort(404);
        }
    }
}
