<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 12:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStepOffersCampaignOverview
{
    use AsObject;

    public function handle(OfferCampaign $offerCampaign): array
    {
        return array_merge(
            GetOfferCampaignOverview::run($offerCampaign),
            [
                'ladder'     => $this->getLadder($offerCampaign),
                'top_offers' => $this->getTopOffers($offerCampaign),
            ]
        );
    }

    public function getLadder(OfferCampaign $offerCampaign): array
    {
        return DB::select(
            "
            select (step->>'min_quantity')::int                                as min_quantity,
                   count(*)                                                    as number_products,
                   round(avg((step->>'percentage_off')::numeric) * 100, 1)    as avg_percentage_off,
                   round(min((step->>'percentage_off')::numeric) * 100, 1)    as min_percentage_off,
                   round(max((step->>'percentage_off')::numeric) * 100, 1)    as max_percentage_off
            from offer_allowances
                     join offers on offers.id = offer_allowances.offer_id
                     cross join lateral jsonb_array_elements(offer_allowances.data->'steps') as step
            where offer_allowances.offer_campaign_id = :campaign_id
              and offers.state = :state
            group by 1
            order by 1
            ",
            [
                'campaign_id' => $offerCampaign->id,
                'state'       => OfferStateEnum::ACTIVE->value,
            ]
        );
    }

    public function getTopOffers(OfferCampaign $offerCampaign): array
    {
        $usage = DB::table('transaction_has_offer_allowances')
            ->where('offer_campaign_id', $offerCampaign->id)
            ->groupBy('offer_id')
            ->select('offer_id', DB::raw('count(distinct order_id) as number_orders'));

        $rows = DB::table('offers')
            ->join('offer_allowances', 'offer_allowances.offer_id', '=', 'offers.id')
            ->leftJoin('products', 'products.id', '=', 'offers.trigger_id')
            ->leftJoinSub($usage, 'offer_usage', 'offer_usage.offer_id', '=', 'offers.id')
            ->where('offers.offer_campaign_id', $offerCampaign->id)
            ->where('offers.state', OfferStateEnum::ACTIVE->value)
            ->selectRaw("
                offers.slug,
                offers.code,
                products.code as product_code,
                products.name as product_name,
                offer_allowances.data->'steps' as steps,
                coalesce(offer_usage.number_orders, 0) as number_orders
            ")
            ->orderByDesc('number_orders')
            ->orderBy('offers.code')
            ->limit(10)
            ->get();

        foreach ($rows as $row) {
            $row->steps = json_decode($row->steps, true);
        }

        return $rows->toArray();
    }
}
