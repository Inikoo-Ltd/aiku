<?php

/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-14h-06m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Collection\SyncIndirectProductsToCollection;
use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\FamilyHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateProducts;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProductsWithNoFamily;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProductsWithNoFamily;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateProductsWithNoFamily;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Rule;

class UpdateProductFamily extends OrgAction
{
    use WithActionUpdate;
    use WithCatalogueEditAuthorisation;

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


            foreach ($product->family->collections as $collection) {
                SyncIndirectProductsToCollection::dispatch($collection);
            }

            if ($oldFamily) {
                FamilyHydrateProducts::dispatch($oldFamily);
                foreach ($oldFamily->collections as $collection) {
                    SyncIndirectProductsToCollection::dispatch($collection);
                }
            } else {
                ShopHydrateProductsWithNoFamily::dispatch($product->shop);
                OrganisationHydrateProductsWithNoFamily::dispatch($product->organisation);
                GroupHydrateProductsWithNoFamily::dispatch($product->group);
            }





        }

        if (Arr::has($changes, 'department_id')) {
            if ($product->department_id) {
                DepartmentHydrateProducts::dispatch($product->department);
            }
            if ($oldDepartment) {
                DepartmentHydrateProducts::dispatch($oldDepartment);
            }
        }

        if (Arr::has($changes, 'sub_department_id')) {
            if ($product->sub_department_id) {
                SubDepartmentHydrateProducts::dispatch($product->subDepartment);
            }
            if ($oldSubDepartment) {
                SubDepartmentHydrateProducts::dispatch($oldSubDepartment);
            }
        }




        return $product;
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
