<?php

namespace App\Actions\Web\WebsiteDialog;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\WithUploadWebImage;
use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToWebsiteDialog extends OrgAction
{
    use WithWebsiteDialogScope;
    use WithUploadWebImage;
    use WithWebEditAuthorisation;

    public function asController(Shop $shop, Website $website, WebsiteDialog $websiteDialog, ActionRequest $request): Collection
    {
        $this->ensureWebsiteDialogScope($shop, $website, $websiteDialog);
        $this->initialisationFromShop($shop, $request);

        return $this->handle($websiteDialog, 'website-dialog', $this->validatedData);
    }
}
