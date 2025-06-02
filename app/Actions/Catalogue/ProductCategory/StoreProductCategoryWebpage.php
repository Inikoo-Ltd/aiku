<?php

/*
 * author Arya Permana - Kirin
 * created on 07-10-2024-14h-21m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreProductCategoryWebpage extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(ProductCategory $productCategory): Webpage
    {
        if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $webpageData = [
                'title'      => $productCategory->name,
                'code'       => $productCategory->code,
                'url'        => strtolower($productCategory->code),
                'sub_type'   => WebpageSubTypeEnum::FAMILY,
                'type'       => WebpageTypeEnum::CATALOGUE,
                'model_type' => class_basename($productCategory),
                'model_id'   => $productCategory->id
            ];
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $webpageData = [
                'title'      => $productCategory->name,
                'code'       => $productCategory->code,
                'url'        => strtolower($productCategory->code),
                'sub_type'   => WebpageSubTypeEnum::SUB_DEPARTMENT,
                'type'       => WebpageTypeEnum::CATALOGUE,
                'model_type' => class_basename($productCategory),
                'model_id'   => $productCategory->id
            ];
        } else {
            $webpageData = [
                'title'      => $productCategory->name,
                'code'       => $productCategory->code,
                'url'        => strtolower($productCategory->code),
                'sub_type'   => WebpageSubTypeEnum::DEPARTMENT,
                'type'       => WebpageTypeEnum::CATALOGUE,
                'model_type' => class_basename($productCategory),
                'model_id'   => $productCategory->id
            ];
        }

        return StoreWebpage::make()->action(
            $productCategory->shop->website,
            $webpageData
        );
    }

    public function htmlResponse(Webpage $webpage): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        return Redirect::route(
            'grp.org.shops.show.web.webpages.show',
            [
                'organisation' => $webpage->organisation->slug,
                'shop'         => $webpage->shop->slug,
                'website'      => $webpage->website->slug,
                'webpage'      => $webpage->slug,
            ]
        );
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): Webpage
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory);
    }

    public function action(ProductCategory $productCategory): Webpage
    {
        $this->asAction = true;
        $this->initialisationFromShop($productCategory->shop, []);

        return $this->handle($productCategory);
    }
}
