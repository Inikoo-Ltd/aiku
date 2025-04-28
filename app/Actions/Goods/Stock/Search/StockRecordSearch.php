<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Goods\Stock\Search;

use App\Models\Goods\Stock;
use Lorisleiva\Actions\Concerns\AsAction;

class StockRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Stock $stock): void
    {

        if ($stock->trashed()) {
            $stock->universalSearch()->delete();

            return;
        }

        $familyName = '';
        if ($stock->stockFamily) {
            $familyName = $stock->stockFamily->name ? ' (' . $stock->stockFamily->name  . ')' : '';
        }
        $stock->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $stock->group_id,
                'sections'          => ['goods'],
                'haystack_tier_1'   => trim($stock->code.' '.$stock->name),
                'keyword'           => $stock->code,
                'result'            => [
                    'route'     => [
                        'name'          => 'grp.goods.stocks.active_stocks.show',
                        'parameters'    => [
                            $stock->slug
                        ]
                    ],
                    'description'      => [
                        'label' => $stock->name . $familyName,
                    ],
                    'code' => [
                        'label' => $stock->code,
                    ],
                    'icon'      => [
                        'icon' => 'fal fa-box',
                    ],
                    'meta'      => [
                        [
                            'label' => $stock->state,
                            'tooltip' => __('State')
                        ],
                        [
                            'type'   => 'number',
                            'label'  => __('Number locations') . ': ',
                            'number' => (int) $stock->stats->number_locations
                        ],

                    ],
                ]
            ]
        );
    }

}
