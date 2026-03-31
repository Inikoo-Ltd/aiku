<?php

namespace App\Actions\Discounts\OfferCampaign;

use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Http\Request;

class StoreGiftsOffers
{
    use AsAction;

    public function handle(array $data)
    {
        // create offer logic
    }

    public function asController(Request $request, $organisation, $shop, $offerCampaign)
    {
        $data = $request->all();

        dd([
            'organisation' => $organisation,
            'shop' => $shop,
            'offerCampaign' => $offerCampaign,
            'payload' => $data
        ]);
    }
}
