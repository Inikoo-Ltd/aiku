<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Procurement\OrgSupplier\Search;

use App\Models\Procurement\OrgSupplier;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgSupplierRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(OrgSupplier $orgSupplier): void
    {

        $supplier = $orgSupplier->supplier;

        if (! $supplier) {
            return;
        }

        $modelData = [
            'group_id' => $orgSupplier->group_id,
            'organisation_id' => $orgSupplier->organisation_id,
            'organisation_slug' => $orgSupplier->organisation->slug,
            'sections' => ['procurement'],
            'haystack_tier_1' => trim($supplier->code.' '.$supplier->name),
            'result' => [
                'route' => [
                    'name' => 'grp.org.procurement.org_suppliers.show',
                    'parameters' => [
                        $orgSupplier->organisation->slug,
                        $orgSupplier->slug,
                    ],
                ],
                'description' => [
                    'label' => $supplier->name,
                ],
                'code' => [
                    'label' => $supplier->code,
                ],
                'icon' => [
                    'icon' => 'fal fa-person-dolly',
                ],
                'meta' => [
                    [
                        'type' => 'location',
                        'location' => $supplier->location,
                        'tooltip' => __('Location'),
                    ],
                    [
                        'type' => 'number',
                        'number' => $orgSupplier->stats->number_org_supplier_products,
                        'label' => __('Suppliers').': ',
                    ],
                    [
                        'type' => 'number',
                        'number' => $orgSupplier->stats->number_purchase_orders,
                        'label' => __('Purchase orders').': ',
                    ],
                ],
            ],
        ];

        $orgSupplier->universalSearch()->updateOrCreate(
            [],
            $modelData
        );

    }
}
