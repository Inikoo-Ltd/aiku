<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 22-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateFamilies;
use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateProducts;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Catalogue\FamilyResource;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Catalogue\ProductCategory;
use Google\Service\Texttospeech\Voice;

class DetachFamilyToSubDepartment extends OrgAction
{
    use WithActionUpdate;

    public function handle(ProductCategory $family): ProductCategory
    {
        $currentSubDepartment = $family->subDepartment;

        $family->update(
            [
                'sub_department_id' => null,
                'department_id'     => $currentSubDepartment->department_id,
                'parent_id'         => $currentSubDepartment->department_id,
            ]
        );

        DB::table('products')->where('family_id', $family->id)->update([
            'sub_department_id' => null,
            'department_id' => $currentSubDepartment->department_id,
        ]);

        ProductCategoryHydrateFamilies::dispatch($currentSubDepartment);
        SubDepartmentHydrateProducts::dispatch($currentSubDepartment);

        return $family;
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
