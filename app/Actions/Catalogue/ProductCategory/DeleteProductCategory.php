<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Validator;

class DeleteProductCategory extends OrgAction
{
    private ProductCategory $productCategory;

    public function handle(ProductCategory $productCategory): ProductCategory
    {
        $productCategory->stats()->delete();
        $productCategory->webpage()->delete();
        $productCategory->delete();
        return $productCategory;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($this->productCategory->getProducts()->count() > 0) {
            $validator->errors()->add('products', 'This category has products associated with it.');
        }

        if ($this->productCategory->children()->exists()) {
            $validator->errors()->add('children', 'This category has sub-categories associated with it.');
        }

    }

    public function htmlResponse(ProductCategory $productCategory, ActionRequest $request)
    {
        return match($productCategory->type) {
            ProductCategoryTypeEnum::DEPARTMENT => Redirect::route('grp.org.shops.show.catalogue.departments.index', [$productCategory->organisation, $productCategory->shop]),
            ProductCategoryTypeEnum::SUB_DEPARTMENT => Redirect::route('grp.org.shops.show.catalogue.departments.show.sub_departments.index', [$productCategory->organisation, $productCategory->shop, $productCategory->parent]),
            default => []
        };
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->productCategory = $productCategory;
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory);
    }



}
