<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 22 Jun 2026 14:02:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class CloseDiscontinuedWebpage
{
    use AsAction;

    public function handle(Webpage $webpage): array
    {
        if (!($webpage->model instanceof Product || $webpage->model instanceof ProductCategory)) {
            return [
                'error' => 'model_not_product_or_product_category '.get_class($webpage->model)
            ];
        }

        if( $webpage->model instanceof ProductCategory && $webpage->model->type !== ProductCategoryTypeEnum::FAMILY ) {
            return [
                'error' => 'product_category_not_family'
            ];
        }


        if($webpage->state !== WebpageStateEnum::LIVE) {
            return [
                'error' => 'webpage_not_live'
            ];
        }


        /** @var Product|ProductCategory $model */
        $model                = $webpage->model;
        $nearestParentWebpage = $this->resolveNearestLiveParent($model);


        CloseWebpage::make()->action($webpage, [
            'redirect_type' => RedirectTypeEnum::PERMANENT,
            'to_webpage_id' => $nearestParentWebpage->id
        ]);

        return [
            'redirect_to' => $nearestParentWebpage->canonical_url
        ];
    }

    protected function resolveNearestLiveParent(ProductCategory|Product $target): Webpage
    {
        $parents = [];

        if ($target instanceof Product) {
            $parents[] = $target->family;
        }

        $parents[] = $target->subDepartment;
        $parents[] = $target->department;

        foreach ($parents as $parent) {
            if (
                !$parent
                || $parent->state === ProductCategoryStateEnum::DISCONTINUED
                || !$parent->webpage
                || $parent->webpage->state !== WebpageStateEnum::LIVE
            ) {
                continue;
            }

            return $parent->webpage;
        }

        // Fallsback to storefront
        return $target->shop->website->storefront;
    }

}
