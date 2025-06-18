<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateFamilies;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Rule;

class UpdateFamilyDepartment extends OrgAction
{
    use WithActionUpdate;

    public function handle(ProductCategory $family, array $modelData): ProductCategory
    {

        $oldDepartment = $family->department;
        $oldSubDepartment = $family->subDepartment;

        data_set($modelData, 'parent_id', Arr::get($modelData, 'department_id'));
        data_set($modelData, 'sub_department_id', null);

        $family = $this->update($family, $modelData);
        $changes         = $family->getChanges();

        $family->refresh();


        if (Arr::has($changes, 'department_id')) {
            ProductCategoryHydrateFamilies::dispatch($family->parent);
            ProductCategoryHydrateFamilies::dispatch($oldDepartment);

        }

        if (Arr::has($changes, 'sub_department_id')) {
            ProductCategoryHydrateFamilies::dispatch($oldSubDepartment);
        }




        return $family;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return [
            'department_id' => [
                'required',
                Rule::exists('product_categories', 'id')
                    ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
                    ->where('shop_id', $this->shop->id)
            ]
        ];
    }

    public function action(ProductCategory $family, array $modelData): ProductCategory
    {
        $this->asAction = true;
        $this->initialisationFromShop($family->shop, $modelData);

        return $this->handle($family, $this->validatedData);
    }

    public function asController(Organisation $organisation, Shop $shop, ProductCategory $family, ActionRequest $request): void
    {
        $this->initialisationFromShop($shop, $request);
        $this->handle($family, $this->validatedData);
    }
}
