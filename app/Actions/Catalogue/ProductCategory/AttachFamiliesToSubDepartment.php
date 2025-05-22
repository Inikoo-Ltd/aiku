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
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Catalogue\SubDepartmentResource;
use App\Models\Catalogue\ProductCategory;

class AttachFamiliesToSubDepartment extends OrgAction
{
    use WithActionUpdate;
    // use WithFulfilmentWarehouseEditAuthorisation;

    public function handle(ProductCategory $subDepartment, array $modelData): ProductCategory
    {
        ProductCategory::whereIn('id', $modelData['families_id'])
            ->update(['sub_department_id' => $subDepartment->id]);
        $subDepartment->refresh();

        return $subDepartment;
    }

    public function rules(): array
    {
        return [
            'families_id' => ['required', 'array'],
            'families_id.*' => [
                'integer',
                'exists:product_categories,id',
            ],
        ];
    }

    public function asController(ProductCategory $subDepartment, ActionRequest $request): ProductCategory
    {

        $this->initialisationFromShop($subDepartment->shop, $request);

        return $this->handle($subDepartment, $this->validatedData);
    }


    public function jsonResponse(ProductCategory $subDepartment): SubDepartmentResource
    {
        return new SubDepartmentResource($subDepartment);
    }
}
