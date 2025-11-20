<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: [Current Date]
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Maintenance\Discounts;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;

class RepairOffersBracket
{
    use WithActionUpdate;

    public string $commandSignature = 'repair:offers-bracket';

    public function asCommand(): void
    {
        $shops = Shop::all();

        foreach ($shops as $shop) {
            $this->handle($shop);
        }
    }

    public function handle(Shop $shop): void
    {
        $offers = Offer::where('shop_id', $shop->id)
            ->with('offerCampaign')
            ->get();

        foreach ($offers as $offer) {
            $campaignCode = $offer->offerCampaign->code ?? null;
            $bracket = $this->determineBracket($campaignCode);

            $this->update($offer, ['bracket' => $bracket]);
        }
    }

    private function determineBracket(?string $code): string
    {
        if (empty($code)) {
            return 'Temporal';
        }

        $ongoingCodes = ['OR', 'VL', 'FO'];
        $discretionaryCodes = ['DI'];

        if (in_array($code, $ongoingCodes)) {
            return 'Ongoing';
        }

        if (in_array($code, $discretionaryCodes)) {
            return 'Discretionary';
        }

        return 'Temporal';
    }
}
