<?php

/*
 * author Arya Permana - Kirin
 * created on 30-05-2025-10h-22m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\ProductCategory\Hydrators\FamilyHydrateProducts;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class MoveFamilyProductToOtherFamily extends OrgAction
{
    use WithActionUpdate;

    public function handle(Product $product, array $modelData): Product
    {
        $product = $this->update($product, $modelData);
        $product->refresh();

        FamilyHydrateProducts::dispatch($product->family);

        return $product;
    }

    public function rules(): array
    {
        return [
            'family_id' => ['required', Rule::exists('product_categories', 'id')->where('shop_id', $this->shop->id)->where('type', ProductCategoryTypeEnum::FAMILY)],
        ];
    }

    public function asController(Product $product, ActionRequest $request)
    {
        $this->initialisationFromShop($product->shop, $request);

        $this->handle($product, $this->validatedData);
    }

}
