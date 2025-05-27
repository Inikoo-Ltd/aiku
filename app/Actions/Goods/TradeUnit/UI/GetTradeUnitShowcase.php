<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Goods\TradeUnit\UI;

use App\Http\Resources\Catalogue\TagsResource;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTradeUnitShowcase
{
    use AsObject;

    public function handle(TradeUnit $tradeUnit): array
    {
        $brandRoute = [
            'store_brand' => [
                'name'       => 'grp.models.trade-unit.brands.store',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ]
            ],
            'update_brand' => [
                'name'       => 'grp.models.trade-unit.brands.update',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'patch'
            ],
            'delete_brand' => [
                'name'       => 'grp.models.trade-unit.brands.delete',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'delete'
            ],
            'attach_brand' => [
                'name'       => 'grp.models.trade-unit.brands.attach',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'post'
            ],
            'detach_brand' => [
                'name'       => 'grp.models.trade-unit.brands.detach',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'delete'
            ],
        ];
        $tagRoute = [
            'index_tag' => [
                'name'       => 'grp.json.trade_units.tags.index',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ]
            ],
            'store_tag' => [
                'name'       => 'grp.models.trade-unit.tags.store',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ]
            ],
            'update_tag' => [
                'name'       => 'grp.models.trade-unit.tags.update',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'patch'
            ],
            'delete_tag' => [
                'name'       => 'grp.models.trade-unit.tags.delete',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'delete'
            ],
            'attach_tag' => [
                'name'       => 'grp.models.trade-unit.tags.attach',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'post'
            ],
            'detach_tag' => [
                'name'       => 'grp.models.trade-unit.tags.detach',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'    => 'delete'
            ],
        ];

        return [
            'brand_routes' => $brandRoute,
            'tag_routes' => $tagRoute,
            'tags_selected_id' => $tradeUnit->tags->pluck('id')->toArray(),
            'tags' =>  TagsResource::collection($tradeUnit->tags)->toArray(request()),
        ];
    }
}
