<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Jul 2024 20:59:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreProductVariant extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Product $product, array $modelData): Product
    {


        $orgStocksData = [];
        foreach ($product->orgStocks as $orgStock) {
            $orgStocksData[$orgStock->id] =
                [
                    'quantity' => $orgStock->pivot->units * $modelData['ratio'],
                    'notes'    => Arr::get($modelData, 'is_main') ? $orgStock->pivot->notes : null
                ];
        }
        data_set($modelData, 'org_stocks', $orgStocksData);
        data_set($modelData, 'organisation_id', $product->organisation_id);
        data_set($modelData, 'group_id', $product->group_id);
        data_set($modelData, 'shop_id', $product->shop_id);
        data_set($modelData, 'asset_id', $product->asset_id);
        data_set($modelData, 'family_id', $product->family_id);
        data_set($modelData, 'department_id', $product->department_id);
        data_set($modelData, 'shop_id', $product->shop_id);
        data_set($modelData, 'currency_id', $product->currency_id);
        data_set($modelData, 'unit', $product->unit);
        data_set($modelData, 'units', $product->units * $modelData['ratio']);

        data_set($modelData, 'status', $product->status);
        data_set($modelData, 'state', $product->state);
        data_set($modelData, 'main_product_id', $product->id);




        return StoreProduct::make()->action(
            parent: $product->shop,
            modelData: $modelData,
            hydratorsDelay: $this->hydratorsDelay,
            strict: $this->strict,
            audit: false
        );


    }


    public function rules(): array
    {
        return [
            'is_main'            => ['required', 'boolean'],
            'ratio'              => ['required', 'numeric', 'gt:0'],
            'code'               => [
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'assets',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'state', 'operator' => '!=', 'value' => ProductStateEnum::DISCONTINUED->value],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'               => ['required', 'max:250', 'string'],
            'price'              => ['required', 'numeric', 'min:0'],
            'source_id'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'historic_source_id' => ['sometimes', 'nullable', 'string', 'max:255'],

        ];
    }


    public function inShop(Product $product, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($product->shop, $request);
        $this->handle($product, $this->validatedData);

        return Redirect::route('grp.org.shops.show.catalogue.products.index', $product->shop);
    }

    public function action(Product $product, array $modelData, int $hydratorsDelay = 0, $strict = true): Product
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;


        $this->initialisationFromShop($product->shop, $modelData);

        return $this->handle($product, $this->validatedData);
    }

}
