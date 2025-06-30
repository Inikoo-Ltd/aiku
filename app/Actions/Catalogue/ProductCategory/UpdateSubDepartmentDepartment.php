<?php
/*
 * author Arya Permana - Kirin
 * created on 30-06-2025-16h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateSubDepartments;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateFamilies;
use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateProducts;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateFamiliesWithNoDepartment;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateFamiliesWithNoDepartment;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateFamiliesWithNoDepartment;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Rule;

class UpdateSubDepartmentDepartment extends OrgAction
{
    use WithActionUpdate;
    use WithCatalogueEditAuthorisation;

    public function handle(ProductCategory $subDepartment, array $modelData): ProductCategory
    {
        $oldDepartment    = $subDepartment->department ?? null;

        data_set($modelData, 'parent_id', Arr::get($modelData, 'department_id'));
        data_set($modelData, 'department_id', Arr::get($modelData, 'department_id'));

        $subDepartment  = $this->update($subDepartment, $modelData);
        $changes = $subDepartment->getChanges();
        $subDepartment->refresh();

        DB::table('product_categories')
            ->where('sub_department_id', $subDepartment->id)
            ->update([
                'department_id'     => $subDepartment->department_id,
            ]);

        DB::table('products')
            ->where('sub_department_id', $subDepartment->id)
            ->update([
                'department_id'     => $subDepartment->department_id,
            ]);

        if (Arr::has($changes, 'department_id')) {
            DepartmentHydrateProducts::dispatch($subDepartment->department);
            DepartmentHydrateSubDepartments::dispatch($subDepartment->department);
            ProductCategoryHydrateFamilies::dispatch($subDepartment->department);
            if ($oldDepartment) {
                DepartmentHydrateProducts::dispatch($oldDepartment);
                ProductCategoryHydrateFamilies::dispatch($oldDepartment);
                DepartmentHydrateSubDepartments::dispatch($oldDepartment);
            } else {
                ShopHydrateFamiliesWithNoDepartment::dispatch($subDepartment->shop);
                OrganisationHydrateFamiliesWithNoDepartment::dispatch($subDepartment->organisation);
                GroupHydrateFamiliesWithNoDepartment::dispatch($subDepartment->group);
            }
        }

        return $subDepartment;
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
