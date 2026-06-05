<?php

namespace App\Actions\Discounts\OfferCampaign;

use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreVoucherOffers
{
    use AsAction;

    public function handle(array $data)
    {
        // create offer logic
    }

    public function asController(Shop $shop, ActionRequest $request)
    {
        dd($request->all());
    }
}
