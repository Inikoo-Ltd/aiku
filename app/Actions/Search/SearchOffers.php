<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchOffers
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $shopId = Arr::get($options, 'shop_id');

        $mapCodeNameState = static fn (array $document) => [
            'id'    => (int)$document['id'],
            'code'  => $document['code'] ?? null,
            'name'  => $document['name'] ?? null,
            'state' => $document['state'] ?? null,
        ];

        $offersQuery = Offer::search($query);
        $campaignsQuery = OfferCampaign::search($query);
        if ($shopId) {
            $offersQuery->where('shop_id', $shopId);
            $campaignsQuery->where('shop_id', $shopId);
        }

        return [
            'scope'   => 'offers',
            'results' => [
                'offers'          => array_map($mapCodeNameState, $this->rawDocuments($offersQuery)),
                'offer_campaigns' => array_map($mapCodeNameState, $this->rawDocuments($campaignsQuery)),
            ],
        ];
    }


}
