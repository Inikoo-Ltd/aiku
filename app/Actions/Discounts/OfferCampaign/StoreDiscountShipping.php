<?php

namespace App\Actions\Discounts\OfferCampaign;

use App\Models\Discounts\OfferCampaign;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreDiscountShipping
{
    use AsAction;

    public function handle()
    {
        // nanti logic create offer
        dd('hello');
    }
}