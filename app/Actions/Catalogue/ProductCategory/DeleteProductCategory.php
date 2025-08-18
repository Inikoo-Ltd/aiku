<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDepartments;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDepartments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDepartments;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Actions\Web\Webpage\DeleteWebpage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Validator;

class DeleteProductCategory extends OrgAction
{
    use WithCatalogueEditAuthorisation;

    private ProductCategory $productCategory;

    /**
     * @throws \Throwable
     */
    public function handle(ProductCategory $productCategory, bool $forceDelete = false): ProductCategory
    {
        if ($forceDelete) {
            if ($productCategory->webpage) {
                DeleteWebpage::make()->action(webpage: $productCategory->webpage, forceDelete: true);
            }

            DB::table('product_category_stats')->where('product_category_id', $productCategory->id)->delete();
            DB::table('product_category_time_series')->where('product_category_id', $productCategory->id)->delete();
            DB::table('product_category_ordering_stats')->where('product_category_id', $productCategory->id)->delete();
            DB::table('product_category_sales_intervals')->where('product_category_id', $productCategory->id)->delete();
            DB::table('product_category_ordering_intervals')->where('product_category_id', $productCategory->id)->delete();


            $productCategory->forceDelete();
        } else {
            if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
                DB::table('products')->where('family_id', $productCategory->id)->update(['family_id' => null]);
            }
            $productCategory->webpage()->delete();
            $productCategory->delete();
        }
        ShopHydrateDepartments::dispatch($productCategory->shop)->delay($this->hydratorsDelay);
        OrganisationHydrateDepartments::dispatch($productCategory->organisation)->delay($this->hydratorsDelay);
        GroupHydrateDepartments::dispatch($productCategory->group)->delay($this->hydratorsDelay);

        return $productCategory;
    }

    /**
     * @throws \Throwable
     */
    public function action(ProductCategory $productCategory, bool $forceDelete = false): ProductCategory
    {
        $this->asAction        = true;
        $this->productCategory = $productCategory;
        $this->initialisationFromShop($productCategory->shop, []);

        return $this->handle($productCategory, $forceDelete);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->productCategory = $productCategory;
        $this->initialisationFromShop($productCategory->shop, $request);

        $forceDelete = $request->boolean('force_delete');

        return $this->handle($productCategory, $forceDelete);
    }


    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($this->productCategory->getProducts()->count() > 0) {
              request()->session()->flash('modal', [
            'status'  => 'error',
            'title'   => __('Failed!'),
            'description' => __('This category has products associated with it.'),
        ]);
            $validator->errors()->add('products', 'This category has products associated with it.');
        }

        if ($this->productCategory->children()->exists()) {
          request()->session()->flash('modal', [
            'status'  => 'error',
            'title'   => __('Failed!'),
            'description' => __('This category has children associated with it.'),
        ]);
         $validator->errors()->add('children', 'This category has children associated with it.');
        }
    }

    public function htmlResponse(ProductCategory $productCategory, ActionRequest $request): \Illuminate\Http\Response|array|\Illuminate\Http\RedirectResponse
    {
        return match ($productCategory->type) {
            ProductCategoryTypeEnum::DEPARTMENT => Redirect::route('grp.org.shops.show.catalogue.departments.index', [$productCategory->organisation, $productCategory->shop]),
            ProductCategoryTypeEnum::SUB_DEPARTMENT => Redirect::route('grp.org.shops.show.catalogue.departments.show.sub_departments.index', [$productCategory->organisation, $productCategory->shop, $productCategory->parent]),
            ProductCategoryTypeEnum::FAMILY => Redirect::route('grp.org.shops.show.catalogue.families.index', [$productCategory->organisation, $productCategory->shop])
        };
    }


}
