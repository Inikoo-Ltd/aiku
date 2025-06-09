<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Actions\Web\Webpage\DeleteWebpage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Validator;

class DeleteProductCategory extends OrgAction
{
    private ProductCategory $productCategory;

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
            $productCategory->webpage()->delete();
            $productCategory->delete();
        }

        return $productCategory;
    }

    public function action(ProductCategory $productCategory, bool $forceDelete = false): ProductCategory
    {
        $this->asAction = true;
        $this->productCategory = $productCategory;
        $this->initialisationFromShop($productCategory->shop, []);
        return $this->handle($productCategory, $forceDelete);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->productCategory = $productCategory;
        $this->initialisationFromShop($productCategory->shop, $request);

        $forceDelete = $request->boolean('force_delete');

        return $this->handle($productCategory, $forceDelete);
    }


    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }


    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($this->productCategory->getProducts()->count() > 0) {
            $validator->errors()->add('products', 'This category has products associated with it.');
        }

        if ($this->productCategory->children()->exists()) {
            $validator->errors()->add('children', 'This category has children associated with it.');
        }
    }

    public function htmlResponse(ProductCategory $productCategory, ActionRequest $request): \Illuminate\Http\Response|array|\Illuminate\Http\RedirectResponse
    {
        return match ($productCategory->type) {
            ProductCategoryTypeEnum::DEPARTMENT => Redirect::route('grp.org.shops.show.catalogue.departments.index', [$productCategory->organisation, $productCategory->shop]),
            ProductCategoryTypeEnum::SUB_DEPARTMENT => Redirect::route('grp.org.shops.show.catalogue.departments.show.sub_departments.index', [$productCategory->organisation, $productCategory->shop, $productCategory->parent]),
            default => []
        };
    }


}
