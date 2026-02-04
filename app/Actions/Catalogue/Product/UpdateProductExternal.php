<?php

/*
 * author Louis Perez
 * created on 04-02-2026-15h-33m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\Traits\WithProductOrgStocks;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Catalogue\ProductResource;
use App\Models\Catalogue\Product;
use App\Stubs\Migrations\HasDangerousGoodsFields;
use App\Stubs\Migrations\HasProductInformation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Validator;

class UpdateProductExternal extends OrgAction
{
    use WithActionUpdate;
    use WithProductHydrators;
    use WithNoStrictRules;

    private Product $product;

    public function handle(Product $product, array $modelData): Product
    {
        if (Arr::has($modelData, 'trade_units')) {
            // Can't do this. Infinite loop until timeout.
            // data_set($modelData, 'is_for_sale', true);
            // data_set($modelData, 'state', ProductStateEnum::ACTIVE);
            $isForSaleOne = $product->is_for_sale;
            $product = UpdateProduct::make()->action($product, $modelData);
            // So had to do this below manually
            $product->update([
                'is_for_sale'           => true,
                'state'                 => ProductStateEnum::ACTIVE,
                'is_single_trade_unit'  => $product->tradeUnits()->count() == 1
            ]);
            $isForSaleTwo = $product->is_for_sale;
            
            CloneProductImagesFromTradeUnits::run($product);
            ProductHydrateAvailableQuantity::run($product);
            
            $this->productHydrators($product, false);
        }


        return $product;
    }

    public function afterValidator(Validator$validator): void
    {
        if ($this->product->shop->type !== ShopTypeEnum::EXTERNAL) {
            $validator->errors()->add('shop', 'This product does not belong to an external shop');
        }
    }

    public function rules(): array
    {
        $rules = [
            'trade_units' => ['sometimes', 'present', 'array'],
        ];

        return $rules;
    }

    public function asController(Product $product, ActionRequest $request): Product
    {
        $this->product = $product;
        $this->initialisationFromShop($product->shop, $request);

        return $this->handle($product, $this->validatedData);
    }

    public function action(Product $product, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Product
    {
        if (!$audit) {
            Product::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->product        = $product;
        $this->strict         = $strict;

        $this->initialisationFromShop($product->shop, $modelData);

        return $this->handle($product, $this->validatedData);
    }

    public function jsonResponse(Product $product): ProductResource
    {
        return new ProductResource($product);
    }
}
