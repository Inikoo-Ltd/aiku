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
use App\Actions\Web\Webpage\UpdateWebpageCanonicalUrl;
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
        $oldFamily        = $product->family;
        $oldDepartment    = $product->department;
        $oldSubDepartment = $product->subDepartment;


        $dataToUpdate = [
            'family_id' => null,
            'department_id' => null,
            'sub_department_id' => null
        ];


        if (Arr::get($modelData, 'family_id')) {
            $family = ProductCategory::find(Arr::get($modelData, 'family_id'));

            if ($family) {
                data_set($dataToUpdate, 'family_id', $family->id);
                data_set($dataToUpdate, 'department_id', $family->department_id);
                data_set($dataToUpdate, 'sub_department_id', $family->sub_department_id);
            }
        }


        $product = $this->update($product, $dataToUpdate);
        $changes = $product->getChanges();

        $product->refresh();


        if (Arr::has($changes, 'family_id')) {
            FamilyHydrateProducts::dispatch($product->family);
            BreakProductInWebpagesCache::make()->breakCache($product->family->webpage);
            if ($product->webpage) {
                UpdateWebpageCanonicalUrl::dispatch($product->webpage, false)->delay(2);
            }


            foreach ($product->family->collections as $collection) {
                SyncIndirectProductsToCollection::dispatch($collection);
            }

            if ($oldFamily) {
                FamilyHydrateProducts::dispatch($oldFamily);
                foreach ($oldFamily->collections as $collection) {
                    SyncIndirectProductsToCollection::dispatch($collection);
                }
                BreakProductInWebpagesCache::make()->breakCache($oldFamily->webpage);
            } else {
                ShopHydrateProductsWithNoFamily::dispatch($product->shop);
                OrganisationHydrateProductsWithNoFamily::dispatch($product->organisation);
                GroupHydrateProductsWithNoFamily::dispatch($product->group);
            }
        }

        if (Arr::has($changes, 'department_id')) {
            if ($product->webpage) {
                UpdateWebpageCanonicalUrl::dispatch($product->webpage, false)->delay(2);
            }
            if ($product->department) {
                BreakProductInWebpagesCache::make()->breakCache($product->department->webpage);
                DepartmentHydrateProducts::dispatch($product->department_id)->delay(2);
            }
            if ($oldDepartment) {
                DepartmentHydrateProducts::dispatch($oldDepartment->id)->delay(2);
                BreakProductInWebpagesCache::make()->breakCache($oldDepartment->webpage);
            }
        }

        if (Arr::has($changes, 'sub_department_id')) {
            if ($product->webpage) {
                UpdateWebpageCanonicalUrl::dispatch($product->webpage, false)->delay(2);
            }
            if ($product->subDepartment) {
                BreakProductInWebpagesCache::make()->breakCache($product->subDepartment->webpage);
                SubDepartmentHydrateProducts::dispatch($product->sub_department_id)->delay(2);
            }
            if ($oldSubDepartment) {
                SubDepartmentHydrateProducts::dispatch($oldSubDepartment->id)->delay(2);
                BreakProductInWebpagesCache::make()->breakCache($oldSubDepartment->webpage);
            }
        }


        return $product;
    }

    public function rules(): array
    {
        return [
            'family_id' => [
                'required',
                'nullable',
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
