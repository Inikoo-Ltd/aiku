<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 21 Oct 2022 08:31:09 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Marketing\Department;

use App\Actions\Marketing\Department\Hydrators\DepartmentHydrateUniversalSearch;
use App\Actions\WithActionUpdate;
use App\Http\Resources\Marketing\DepartmentResource;
use App\Models\Marketing\ProductCategory;
use Lorisleiva\Actions\ActionRequest;

class UpdateDepartment
{
    use WithActionUpdate;


    private bool $asAction=false;

    public function handle(ProductCategory $productCategory, array $modelData): ProductCategory
    {
        $productCategory = $this->update($productCategory, $modelData, ['data']);
        DepartmentHydrateUniversalSearch::dispatch($productCategory);
        return $productCategory;
    }

    public function authorize(ActionRequest $request): bool
    {
        if($this->asAction) {
            return true;
        }

        return $request->user()->hasPermissionTo("shops.products.edit");
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'unique:tenant.product_categories', 'between:2,9', 'alpha'],
            'name' => ['required', 'max:250', 'string'],
            'image_id' => ['sometimes', 'required', 'exists:media,id'],
            'state' => ['sometimes', 'required'],
            'description' => ['sometimes', 'required', 'max:1500'],
        ];
    }

    public function action(ProductCategory $productCategory, array $objectData): ProductCategory
    {
        $this->asAction=true;
        $this->setRawAttributes($objectData);
        $validatedData = $this->validateAttributes();

        return $this->handle($productCategory, $validatedData);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $request->validate();
        return $this->handle($productCategory, $request->all());
    }

    public function jsonResponse(ProductCategory $productCategory): DepartmentResource
    {
        return new DepartmentResource($productCategory);
    }
}
