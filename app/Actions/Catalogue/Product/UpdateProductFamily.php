<?php
/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-14h-06m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\FamilyHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateFamilies;
use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateProducts;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Rule;

class UpdateProductFamily extends OrgAction
{
    use WithActionUpdate;

    public function handle(Product $product, array $modelData): Product
    {

        $oldFamily = $product->family;
        $oldDepartment = $product->department;
        $oldSubDepartment = $product->subDepartment;

        $family = ProductCategory::find(Arr::get($modelData, 'family_id'));

        data_set($modelData, 'family_id', Arr::get($modelData, 'family_id'));
        data_set($modelData, 'department_id', $family->department_id);
        data_set($modelData, 'sub_department_id', $family->sub_department_id);

        $product = $this->update($product, $modelData);
        $changes         = $product->getChanges();

        $product->refresh();


        if (Arr::has($changes, 'family_id')) {
            FamilyHydrateProducts::dispatch($product->family);
            if ($oldFamily) {
                FamilyHydrateProducts::dispatch($oldFamily);
            }
        }

        if (Arr::has($changes, 'department_id')) {
            if ($product->department) {
                DepartmentHydrateProducts::dispatch($product->department);
            }
            if ($oldDepartment) {
                DepartmentHydrateProducts::dispatch($oldDepartment);
            }
        }

        if (Arr::has($changes, 'sub_department_id')) {
            if ($product->department) {
                SubDepartmentHydrateProducts::dispatch($product->oldSubDepartment);
            }
            if ($oldSubDepartment) {
                SubDepartmentHydrateProducts::dispatch($oldSubDepartment);
            }
        }

        return $product;
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
            'family_id' => [
                'required',
                Rule::exists('product_categories', 'id')
                    ->where('type', ProductCategoryTypeEnum::FAMILY)
                    ->where('shop_id', $this->shop->id)
            ]
        ];
    }

    public function action(Product $product, array $modelData): Product
    {
        $this->asAction = true;
        $this->initialisationFromShop($product->shop, $modelData);

        return $this->handle($product, $this->validatedData);
    }

    public function asController(Product $product, ActionRequest $request): void
    {
        $this->initialisationFromShop($product->shop, $request);
        $this->handle($product, $this->validatedData);
    }
}
