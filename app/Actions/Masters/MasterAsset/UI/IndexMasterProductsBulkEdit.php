<?php

/*
 * Author: Vika Aqordi
 * Created on 22-12-2025-16h-04m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterFamilySubNavigation;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Http\Resources\Masters\MasterProductsResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexMasterProductsBulkEdit extends GrpAction
{
    use WithMasterCatalogueSubNavigation;
    use WithMasterDepartmentSubNavigation;
    use WithMasterFamilySubNavigation;
    use WithMastersAuthorisation;

    private Group|MasterShop|MasterProductCategory $parent;

    public function handle(Group|MasterShop|MasterProductCategory $parent, $prefix = null): Group|MasterShop|MasterProductCategory
    {
        return $parent;
    }


    public function jsonResponse(Group|MasterShop|MasterProductCategory $masterAssets): AnonymousResourceCollection
    {
        return MasterProductsResource::collection($masterAssets);
    }

    public function htmlResponse(Group|MasterShop|MasterProductCategory $masterAssets, ActionRequest $request): Response
    {
        $title = __('Bulk edit Master Products');

        return Inertia::render(
            'Masters/MasterProductsBulkEdit',
            [
                'title'                 => $title,
                'pageHead'              => [
                    'model'         => __('Master Products'),
                    'title'         => __('Bulk Edit'),
                ],

            ]
        );
    }

    public function asController(ActionRequest $request): Group|MasterShop|MasterProductCategory
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisation($group, $request);

        return $this->handle($group);
    }

}
