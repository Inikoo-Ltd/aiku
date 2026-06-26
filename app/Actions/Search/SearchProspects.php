<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:18:32 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Http\Resources\CRM\Prospect\ProspectSearchResultResource;
use App\Models\CRM\Prospect;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchProspects
{
    use AsAction;

    public function handle(string $query, array $options): array
    {
        $prospectsQuery = Prospect::search($query);
        if ($shopId = Arr::get($options, 'shop_id')) {
            $prospectsQuery->where('shop_id', $shopId);
        }


        return [
            'scope'   => 'prospects',
            'results' => [
                'prospects' => ProspectSearchResultResource::collection($prospectsQuery->get()),

            ],
        ];
    }


}
