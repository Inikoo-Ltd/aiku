<?php

namespace App\Actions\Discounts\OfferCampaign;

use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Http\Request;

class StoreCustomerOffers
{
    use AsAction;

    public function handle(array $data)
    {
        // create offer logic
    }

    public function asController(Shop $shop,Request $request)
    {
        $data = $request->all();
        dd($data);

    }
}
