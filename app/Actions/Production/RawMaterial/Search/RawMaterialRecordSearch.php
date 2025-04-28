<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 23:00:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\RawMaterial\Search;

use App\Models\Goods\Stock;
use App\Models\Production\RawMaterial;
use Lorisleiva\Actions\Concerns\AsAction;

class RawMaterialRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(RawMaterial $rawMaterial): void
    {
        if ($rawMaterial->trashed()) {
            $rawMaterial->universalSearch()->delete();

            return;
        }

        /** @var Stock $stock */
        $stock = $rawMaterial->stock;

        $rawMaterial->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $rawMaterial->group_id,
                'organisation_id'   => $rawMaterial->organisation_id,
                'organisation_slug' => $rawMaterial->organisation->slug,
                'sections'          => ['productions'],
                'haystack_tier_1'   => trim($rawMaterial->code . ' ' . $stock->code),
                'result'            => [
                    'route'      => [
                        'name'       => 'grp.org.productions.show.crafts.raw_materials.show',
                        'parameters' => [
                            'organisation' => $rawMaterial->organisation->slug,
                            'production'     => $rawMaterial->production->slug,
                            'rawMaterial'     => $rawMaterial->slug,
                        ]
                    ],
                    'description' => [
                        'label'     => $stock->name . ' (' . $rawMaterial->description . ')',
                    ],
                    'code' => [
                        'label' => $rawMaterial->code
                    ],
                    'icon'  => [
                        'icon' => 'fal fa-drone',
                    ],
                    'meta'  => [
                        [
                            'label'   => $rawMaterial->state->labels()[$rawMaterial->state->value],
                            'tooltip' => __('State')
                        ],
                        [
                            'label'   => $rawMaterial->stock_status->labels()[$rawMaterial->stock_status->value],
                            'tooltip' => __('Stock Status')
                        ],
                        [
                            'label'   => $rawMaterial->type,
                            'tooltip' => __('Type')
                        ],
                        [
                            'label'   => $rawMaterial->unit->labels()[$rawMaterial->unit->value] . ' ' . $rawMaterial->unit_cost,
                            'tooltip' => __('Stock (Units)')
                        ],
                        [
                            'type'      => 'number',
                            'label'     => __('Quantity on Location') . ': ',
                            'number'    => $rawMaterial->quantity_on_location,
                        ],
                    ]
                ]
            ]
        );
    }

}
