<?php

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithGoogleAdsEditAuthorisation
{
    use WithShopPpcPermissions;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canEditGoogleAds($request->user(), $this->shop);
    }
}
