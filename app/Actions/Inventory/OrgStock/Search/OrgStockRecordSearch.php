<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Inventory\OrgStock\Search;

use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(OrgStock $orgStock): void
    {
        #TODO: bug, inventory just only can search in the first warehouse of organisation
        # if organisation have many warehouse this will not work for the other except the first one
        # solution:
        # create new table 'warehouse_has_org_stock_family'
        # or make inventory outside of warehouse

        if ($orgStock->trashed()) {
            $orgStock->universalSearch()->delete();

            return;
        }

        $warehouse = $orgStock->organisation->warehouses->first();

        if (!$warehouse) {
            return;
        }

        $orgFamilyName = '';
        if ($orgStock->orgStockFamily) {
            $orgFamilyName = $orgStock->orgStockFamily->name ? ' ('.$orgStock->orgStockFamily->name.')' : '';
        }
        $orgStock->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $orgStock->group_id,
                'organisation_id'   => $orgStock->organisation_id,
                'organisation_slug' => $orgStock->organisation->slug,
                'warehouse_id'      => $warehouse->id,
                'warehouse_slug'    => $warehouse->slug,
                'sections'          => ['inventory'],
                'haystack_tier_1'   => trim($orgStock->code.' '.$orgStock->name),
                'keyword'           => $orgStock->code,
                'result'            => [
                    'route'       => [
                        'name'       => 'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show',
                        'parameters' => [
                            $orgStock->organisation->slug,
                            $warehouse->slug,
                            $orgStock->slug
                        ]
                    ],
                    'description' => [
                        'label' => $orgStock->name.$orgFamilyName,
                    ],
                    'code'        => [
                        'label' => $orgStock->code,
                    ],
                    'icon'        => [
                        'icon' => 'fal fa-box',
                    ],
                    'meta'        => [
                        [
                            'label'   => $orgStock->state,
                            'tooltip' => __('State')
                        ],
                        [
                            'type'   => 'number',
                            'label'  => __('Number locations').': ',
                            'number' => $orgStock->stats->number_locations
                        ],

                    ],
                ]
            ]
        );
    }

}
