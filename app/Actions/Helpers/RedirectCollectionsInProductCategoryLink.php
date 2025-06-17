<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Jun 2025 21:51:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectCollectionsInProductCategoryLink extends OrgAction
{
    public function handle(ProductCategory $productCategory): ?RedirectResponse
    {
        if ($productCategory->type === ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            return Redirect::route(
                'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index',
                [
                    $productCategory->organisation->slug,
                    $productCategory->shop->slug,
                    $productCategory->department->slug,
                    $productCategory->slug
                ]
            );
        } elseif ($productCategory->type === ProductCategoryTypeEnum::DEPARTMENT) {
            return Redirect::route(
                'grp.org.shops.show.catalogue.departments.show.collection.index',
                [
                    $productCategory->organisation->slug,
                    $productCategory->shop->slug,
                    $productCategory->slug,
                ]
            );
        } else {
            return Redirect::route(
                'grp.org.shops.show.catalogue.collections.index',
                [
                    $productCategory->organisation->slug,
                    $productCategory->shop->slug,
                ]
            );
        }
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory);
    }

}
