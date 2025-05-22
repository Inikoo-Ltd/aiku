<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 22-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Catalogue\FamilyResource;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Catalogue\ProductCategory;

class DetachFamilyToSubDepartment extends OrgAction
{
    use WithActionUpdate;
    // use WithFulfilmentWarehouseEditAuthorisation;

    // TODO: check this
    public function handle(ProductCategory $family): ProductCategory
    {

        $family->update(['sub_department_id' => null]);
        $family->refresh();

        return $family;
    }

    public function asController(ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): ProductCategory
    {

        $this->initialisationFromShop($family->shop, $request);

        return $this->handle($family, $this->validatedData);
    }


    public function jsonResponse(ProductCategory $family): FamilyResource
    {
        return new FamilyResource($family);
    }
}
