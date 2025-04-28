<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Goods\StockFamily\Search;

use App\Models\Goods\StockFamily;
use Lorisleiva\Actions\Concerns\AsAction;

class StockFamilyRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(StockFamily $stockFamily): void
    {

        if ($stockFamily->trashed()) {
            $stockFamily->universalSearch()->delete();

            return;
        }

        $stockFamily->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $stockFamily->group_id,
                'sections'          => ['goods'],
                'haystack_tier_1'   => trim($stockFamily->code.' '.$stockFamily->name),
                'keyword'           => $stockFamily->code,
                'result'            => [
                    'route'     => [
                        'name'          => 'grp.goods.stock-families.show',
                        'parameters'    => [
                            $stockFamily->slug
                        ]
                    ],
                    'description'      => [
                        'label' => $stockFamily->name,
                    ],
                    'code' => [
                        'label' => $stockFamily->code,
                    ],
                    'icon'      => [
                        'icon' => 'fal fa-boxes-alt',
                    ],
                    'meta'      => [
                        [
                            'label' => $stockFamily->state,
                            'tooltip' => __('State')
                        ],
                        [
                            'type'   => 'number',
                            'label'  => __('SKUs') . ': ',
                            'number' => (int) $stockFamily->stats->number_current_org_stocks
                        ],

                    ],
                ]
            ]
        );
    }

}
