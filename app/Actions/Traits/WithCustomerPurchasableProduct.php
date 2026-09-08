<?php

/*
 * Author: Vika Aqordi
 * Created on 11-08-2026
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Traits;

use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use Illuminate\Validation\ValidationException;

trait WithCustomerPurchasableProduct
{
    protected function isProductPurchasableByCustomer(Product $product): bool
    {
        // Don't allow customers to purchase products (example: UK: Gold Reward Bonus)
        return (float)$product->price > 0;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureProductIsPurchasableByCustomer(?Product $product): void
    {
        if (!$product instanceof Product) {
            return;
        }

        if ($this->isProductPurchasableByCustomer($product)) {
            return;
        }

        throw ValidationException::withMessages([
            'message' => __('This product is not available for purchase.'),
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureHistoricAssetIsPurchasableByCustomer(?HistoricAsset $historicAsset): void
    {
        $model = $historicAsset?->model;

        $this->ensureProductIsPurchasableByCustomer($model instanceof Product ? $model : null);
    }
}
