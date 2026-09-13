<?php

/*
 * Author: Vika Aqordi
 * Created on 11-08-2026
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Traits;

use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use Illuminate\Validation\ValidationException;

trait WithCustomerPurchasableProduct
{
    /**
     * Free products are rewards, not for sale (example: UK Gold Reward Bonus). A public product
     * must be for sale. An exclusive product is never for sale publicly: it can be bought only by
     * the customers it is exclusive to, and only while there is stock. An on demand product is made
 * to order and never out of stock. Out of stock, coming soon,
     * discontinued and not-for-sale products never enter a basket, a line for them would be
     * charged and could not be sent.
     */
    protected function isProductPurchasableByCustomer(Product $product, Customer $customer): bool
    {
        if ((float) $product->price <= 0) {
            return false;
        }

        if ($product->exclusive_for_customer_id) {
            $isAllowed = $product->exclusive_for_customer_id == $customer->id
                || $product->exclusiveCustomers()->where('customers.id', $customer->id)->exists();

            return $isAllowed && ($product->is_on_demand || $product->available_quantity > 0);
        }

        return $product->status == ProductStatusEnum::FOR_SALE
            || ($product->is_on_demand && $product->status == ProductStatusEnum::OUT_OF_STOCK);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureProductIsPurchasableByCustomer(?Product $product, Customer $customer): void
    {
        if (!$product instanceof Product) {
            return;
        }

        if ($this->isProductPurchasableByCustomer($product, $customer)) {
            return;
        }

        throw ValidationException::withMessages([
            'message' => __('This product is not available for purchase.'),
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureHistoricAssetIsPurchasableByCustomer(?HistoricAsset $historicAsset, Customer $customer): void
    {
        $model = $historicAsset?->model;

        $this->ensureProductIsPurchasableByCustomer($model instanceof Product ? $model : null, $customer);
    }
}
