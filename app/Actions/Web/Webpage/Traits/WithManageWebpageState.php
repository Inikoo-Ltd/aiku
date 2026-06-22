<?php

/*
 * author Louis Perez
 * created on 22-06-2026-10h-36m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\Webpage\Traits;

use App\Actions\Web\Webpage\CloseWebpage;
use App\Actions\Web\Webpage\ReopenWebpage;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;

trait WithManageWebpageState
{
    public function handleWebpageState(ProductCategory|Product $target): void
    {
        $webpage = $target->webpage;

        // Skip if has no webpage
        if (!$webpage) {
            return;
        }

        // If in_process/discontinued, just set webpage to offline
        $setAsOffline   = in_array($target->state, [
            ProductStateEnum::DISCONTINUED,
            ProductCategoryStateEnum::DISCONTINUED,
            ProductStateEnum::IN_PROCESS,
            ProductCategoryStateEnum::IN_PROCESS
        ]);

        if ($setAsOffline) {
            $nearestParentWebpage = $this->resolveNearestLiveParent($target);

            CloseWebpage::make()->action($webpage, [
                'redirect_type' => RedirectTypeEnum::PERMANENT,
                'to_webpage_id' => $nearestParentWebpage->id
            ]);
        } else {
            ReopenWebpage::run($webpage);
        }
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
                !$parent ||
                $parent->state === ProductCategoryStateEnum::DISCONTINUED ||
                !$parent->webpage ||
                $parent->webpage->state !== WebpageStateEnum::LIVE
            ) {
                continue;
            }

            return $parent->webpage;
        }

        // Fallsback to storefront
        return $target->shop->website->storefront;
    }
}
