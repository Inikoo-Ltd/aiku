<?php

namespace App\Actions\Discounts\OfferCampaign;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDiscountsEditAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class StoreProductOffers extends OrgAction
{
    use WithDiscountsEditAuthorisation;

    public function handle(array $data)
    {
        // create offer logic
    }

    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request)
    {
        $this->initialisationFromShop($shop, $request);

        $data = $request->all();

        dd([
            'organisation' => $organisation,
            'shop' => $shop,
            'offerCampaign' => $offerCampaign,
            'payload' => $data
        ]);
    }
}
