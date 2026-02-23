<?php

/*
 * author Louis Perez
 * created on 04-02-2026-15h-33m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Actions\OrgAction;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Catalogue\ProductResource;
use App\Models\Catalogue\Product;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Validator;

class UpdateTradeUnitsForExternalProduct extends OrgAction
{
    use WithActionUpdate;
    use WithProductHydrators;
    use WithNoStrictRules;

    private Product $product;

    public function handle(Product $product, array $modelData): Product
    {
        $product = UpdateProduct::make()->action($product, $modelData);

        $product->update([
            'is_for_sale' => true,
            'state'       => ProductStateEnum::ACTIVE,
        ]);
        ModelHydrateSingleTradeUnits::run($product);

        CloneProductImagesFromTradeUnits::run($product);
        ProductHydrateAvailableQuantity::run($product);

        $this->productHydrators($product, false);

        return $product;
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->product->shop->type !== ShopTypeEnum::EXTERNAL) {
            $validator->errors()->add('shop', 'This product does not belong to an external shop');
        }
    }

    public function rules(): array
    {
        return [
            'trade_units' => ['required', 'array'],
            'trade_units.*.id' => ['required', 'exists:trade_units,id'],
            'trade_units.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function asController(Product $product, ActionRequest $request): Product
    {
        $this->product = $product;
        $this->initialisationFromShop($product->shop, $request);

        return $this->handle($product, $this->validatedData);
    }

    public function action(Product $product, array $modelData): Product
    {
        $this->asAction = true;
        $this->product  = $product;

        $this->initialisationFromShop($product->shop, $modelData);

        return $this->handle($product, $this->validatedData);
    }

    public function jsonResponse(Product $product): ProductResource
    {
        return new ProductResource($product);
    }
}
