<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\GrpAction;
use App\Actions\Traits\WithMasterProductCategoryListing;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Http\Resources\Masters\MasterDepartmentsResource;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetMasterSubDepartments extends GrpAction
{
    use WithMasterProductCategoryListing;

    public function asController(MasterShop $masterShop, MasterCollection $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($masterShop->group, $request);

        return $this->handle(masterShop: $masterShop, masterCollection: $scope);
    }

    public function handle(MasterShop $masterShop, MasterCollection $masterCollection, $prefix = null): LengthAwarePaginator
    {
        return $this->buildMasterCategoryPaginator(
            masterShop: $masterShop,
            masterCollection: $masterCollection,
            type: MasterProductCategoryTypeEnum::SUB_DEPARTMENT,
            excludeRelationMethod: 'parentMasterSubDepartments',
            prefix: $prefix
        );
    }

    public function jsonResponse(LengthAwarePaginator $departments): AnonymousResourceCollection
    {
        return MasterDepartmentsResource::collection($departments);
    }
}
