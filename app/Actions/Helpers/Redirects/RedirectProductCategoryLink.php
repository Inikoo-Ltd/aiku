<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 02:58:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectProductCategoryLink extends OrgAction
{
    public function handle(ProductCategory $productCategory): ?RedirectResponse
    {
        if ($productCategory->type === ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            return Redirect::route(
                'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
                [
                    $productCategory->organisation->slug,
                    $productCategory->shop->slug,
                    $productCategory->department->slug,
                    $productCategory->slug,
                ]
            );
        } elseif ($productCategory->type === ProductCategoryTypeEnum::DEPARTMENT) {
            return Redirect::route(
                'grp.org.shops.show.catalogue.departments.show',
                [
                    $productCategory->organisation->slug,
                    $productCategory->shop->slug,
                    $productCategory->slug,
                ]
            );
        } else {
            return Redirect::route(
                'grp.org.shops.show.catalogue.families.show',
                [
                    $productCategory->organisation->slug,
                    $productCategory->shop->slug,
                    $productCategory->slug,
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
