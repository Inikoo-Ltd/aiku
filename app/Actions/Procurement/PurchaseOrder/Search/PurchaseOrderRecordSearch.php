<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Procurement\PurchaseOrder\Search;

use App\Models\Procurement\PurchaseOrder;
use Lorisleiva\Actions\Concerns\AsAction;

class PurchaseOrderRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(PurchaseOrder $purchaseOrder): void
    {

        $modelData = [
            'group_id' => $purchaseOrder->group_id,
            'organisation_id' => $purchaseOrder->organisation_id,
            'organisation_slug' => $purchaseOrder->organisation->slug,
            'sections' => ['procurement'],
            'haystack_tier_1' => trim($purchaseOrder->reference.' '.$purchaseOrder->parent_name),
            'result' => [
                'route' => [
                    'name' => 'grp.org.procurement.purchase_orders.show',
                    'parameters' => [
                        $purchaseOrder->organisation->slug,
                        $purchaseOrder->slug,
                    ],
                ],
                'description' => [
                    'label' => $purchaseOrder->parent_name,
                ],
                'code' => [
                    'label' => $purchaseOrder->reference,
                ],
                'icon' => [
                    'icon' => 'fal fa-clipboard-list',
                ],
                'meta' => [
                    [
                        'icon' => $purchaseOrder->state->stateIcon()[$purchaseOrder->state->value],
                        'label' => __($purchaseOrder->state->labels()[$purchaseOrder->state->value]),
                        'tooltip' => __('State'),
                    ],
                    [
                        'type' => 'date',
                        'label' => $purchaseOrder->created_at,
                        'tooltip' => __('Date created'),
                    ],
                    [
                        'type' => 'number',
                        'number' => $purchaseOrder->number_of_items,
                        'label' => __('Items').': ',
                    ],
                    [
                        'type' => 'currency',
                        'amount' => $purchaseOrder->cost_total,
                        'tooltip' => __('Amount'),
                    ],
                ],
            ],
        ];

        $purchaseOrder->universalSearch()->updateOrCreate(
            [],
            $modelData
        );

    }
}
