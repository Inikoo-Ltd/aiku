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
use App\Http\Resources\Catalogue\FamilyResource;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Catalogue\ProductCategory;

class DetachFamilyToSubDepartment extends OrgAction
{
    public function handle(ProductCategory $family): ProductCategory
    {
        return UpdateFamilyDepartment::make()->action($family, [
            'department_id' => $family->subDepartment->department_id,
        ]);
    }

    public function asController(ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): void
    {
        $this->initialisationFromShop($family->shop, $request);

        $this->handle($family);
    }


    public function jsonResponse(ProductCategory $family): FamilyResource
    {
        return new FamilyResource($family);
    }
}
