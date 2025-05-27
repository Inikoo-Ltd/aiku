<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\Helpers\Tag\Json\GetTags;
use App\Http\Resources\Catalogue\TagsResource;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTradeUnitShowcase
{
    use AsObject;

    public function handle(TradeUnit $tradeUnit): array
    {
        $tagRoute = [
                        'indexJson' => [
                            'name'       => 'grp.json.trade_units.tags.index',
                            'parameters' => [
                                'tradeUnit' => $tradeUnit->id,
                            ]
                        ],
                        'store' => [
                            'name'       => 'grp.models.trade-unit.tags.store',
                            'parameters' => [
                                'tradeUnit' => $tradeUnit->id,
                            ]
                        ],
                        'update' => [
                            'name'       => 'grp.models.trade-unit.tags.update',
                            'parameters' => [
                                'tradeUnit' => $tradeUnit->id,
                            ],
                            'method'    => 'patch'
                        ],
                        'destroy' => [
                            'name'       => 'grp.models.trade-unit.tags.destroy',
                            'parameters' => [
                                'tradeUnit' => $tradeUnit->id,
                            ],
                            'method'    => 'delete'
                        ],
                        'attach' => [
                            'name'       => 'grp.models.trade-unit.tags.attach',
                            'parameters' => [
                                'tradeUnit' => $tradeUnit->id,
                            ],
                            'method'    => 'post'
                        ],
                        'detach' => [
                            'name'       => 'grp.models.trade-unit.tags.detach',
                            'parameters' => [
                                'tradeUnit' => $tradeUnit->id,
                            ],
                            'method'    => 'delete'
                        ],
                    ];

        return [
            'tagRoute' => $tagRoute,
            'tags' =>  TagsResource::collection(GetTags::run($tradeUnit)),
        ];
    }
}
