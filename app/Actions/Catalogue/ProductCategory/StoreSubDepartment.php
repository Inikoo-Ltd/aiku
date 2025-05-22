<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 21 Oct 2022 08:27:33 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreSubDepartment extends OrgAction
{
    public function handle(ProductCategory $department, array $modelData): ProductCategory
    {
        data_set($modelData, 'type', ProductCategoryTypeEnum::SUB_DEPARTMENT);

        return StoreProductCategory::run($department, $modelData);
    }

    public function rules(): array
    {
        return [
            'code'        => [
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'product_categories',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'        => ['required', 'max:250', 'string'],
            'description' => ['sometimes', 'required', 'max:1500'],

        ];
    }


    public function asController(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory, $this->validatedData);
    }


    public function htmlResponse(ProductCategory $productCategory, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.catalogue.departments.show.sub_departments.show', [
            'organisation'  => $productCategory->organisation->slug,
            'shop'          => $productCategory->shop->slug,
            'department'    => $productCategory->parent->slug,
            'subDepartment' => $productCategory->slug,
        ]);
    }


}
