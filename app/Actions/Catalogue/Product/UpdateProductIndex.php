<?php

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;

class UpdateProductIndex extends OrgAction
{
    public function handle(ProductCategory $productCategory, array $modelData): void
    {
        if ($productCategory->type !== ProductCategoryTypeEnum::FAMILY) {
            abort(403, "Unable to modify this product index");
        }

        $indexOrders = collect(data_get($modelData, 'products', []))->keyBy('code')->toArray();
        $products = Product::whereIn('code', array_keys($indexOrders))->where('shop_id', $this->shop->id)->get();

        foreach ($products as $product) {
            $product->updateQuietly([
                "index_under_{$productCategory->type->value}"    => data_get($indexOrders, "{$product->code}.index_under_{$productCategory->type->value}", null)
            ]);
        };
    }

    public function rules(): array
    {
        return [
            'products.*.id'                             => ['required', 'numeric'],
            'products.*.code'                           => ['required', 'string'],
            'products.*.index_under_family'             => ['sometimes', 'numeric', "gte:0"],
        ];
    }

    public function asAction(ProductCategory $productCategory, array $modelData): void
    {
        $this->initialisationFromShop($productCategory->shop, $modelData);

        $this->handle($productCategory, $this->validatedData);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): void
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        $this->handle($productCategory, $this->validatedData);
    }
}
