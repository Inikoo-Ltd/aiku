<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Http\Resources\Masters\MasterFamilyWebsiteOrderResource;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterDepartmentFamiliesOrder
{
    use AsObject;

    public function handle(MasterProductCategory $masterDepartment): array
    {
        $families           = GetMasterDepartmentFamilies::run($masterDepartment)->load(['masterSubDepartment', 'stats']);
        $collectionFamilies = GetMasterDepartmentFamilies::make()->getCollectionFamilies($masterDepartment)->load(['masterDepartment', 'masterSubDepartment', 'stats']);

        return [
            'id'                  => $masterDepartment->id,
            'data'                => MasterFamilyWebsiteOrderResource::collection($families),
            'collection_families' => MasterFamilyWebsiteOrderResource::collection($collectionFamilies),
            'editable'            => true,
            'number_uncurated'    => $families->whereNull('website_position')->count(),
            'payload_key'         => 'master_families',
            'route_save_order'    => [
                'name'       => 'grp.models.master_product_category.families_order.update',
                'parameters' => [
                    'masterProductCategory' => $masterDepartment->id
                ]
            ],
        ];
    }
}
