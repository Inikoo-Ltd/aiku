<?php

namespace App\Actions\Discounts\OfferCampaign;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreVoucherOffers extends OrgAction
{
    use AsAction;

    public function handle(Shop $shop, array $modelData)
    {
    }

    public function asController(Shop $shop, ActionRequest $request)
    {
        $this->initialisationFromShop($shop, $request);
        $this->handle($shop, $this->validatedData);
    }
}
