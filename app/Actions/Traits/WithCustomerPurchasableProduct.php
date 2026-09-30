<?php

/*
 * Author: Vika Aqordi
 * Created on 11-08-2026
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Traits;

use App\Actions\Ordering\PreOrder\GetProductPreOrder;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

trait WithCustomerPurchasableProduct
{
    /**
     * Free products are rewards, not for sale (example: UK Gold Reward Bonus), and a customer buys
     * only from their own shop. A public product must be for sale. An exclusive product is never
     * for sale publicly: it can be bought only by the customers it is exclusive to, and only while
     * there is stock. An on demand product is made to order and never out of stock. A product
     * offered for pre-order (back-order or made-to-order, HELP-3432) is bought beyond its stock
     * and sent when the goods arrive. Any other out of stock, coming soon, discontinued and
     * not-for-sale product never enters a basket, a line for it would be charged and could not
     * be sent.
     * Callers checking many products pass the customer's exclusive product ids (keyed by id) to
     * avoid a query per product.
     */
    protected function isProductPurchasableByCustomer(Product $product, Customer $customer, ?Collection $customerExclusiveProductIds = null): bool
    {
        if ((float) $product->price <= 0 || $product->shop_id != $customer->shop_id) {
            return false;
        }

        if ($product->exclusive_for_customer_id) {
            $isAllowed = $product->exclusive_for_customer_id == $customer->id
                || ($customerExclusiveProductIds
                    ? $customerExclusiveProductIds->has($product->id)
                    : $product->exclusiveCustomers()->where('customers.id', $customer->id)->exists());

            return $isAllowed && ($product->is_on_demand || $product->available_quantity > 0);
        }

        return $product->status == ProductStatusEnum::FOR_SALE
            || ($product->is_on_demand && $product->status == ProductStatusEnum::OUT_OF_STOCK)
            || (in_array($product->status, [ProductStatusEnum::OUT_OF_STOCK, ProductStatusEnum::COMING_SOON]) && GetProductPreOrder::make()->type($product));
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureProductIsPurchasableByCustomer(?Product $product, Customer $customer, mixed $quantity = null): void
    {
        if (!$product instanceof Product) {
            return;
        }

        if (!$this->isProductPurchasableByCustomer($product, $customer)) {
            throw ValidationException::withMessages([
                'message' => __('This product is not available for purchase.'),
            ]);
        }

        $this->ensureQuantityWithinProductLimit($product, $quantity);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureQuantityWithinProductLimit(Product $product, mixed $quantity): void
    {
        if ($product->max_quantity_per_order && $quantity !== null && (float) $quantity > $product->max_quantity_per_order) {
            throw ValidationException::withMessages([
                'message' => __('You can order up to :quantity of :product per order', ['quantity' => $product->max_quantity_per_order, 'product' => $product->code]),
            ]);
        }
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureHistoricAssetIsPurchasableByCustomer(?HistoricAsset $historicAsset, Customer $customer, mixed $quantity = null): void
    {
        $model = $historicAsset?->model;

        $this->ensureProductIsPurchasableByCustomer($model instanceof Product ? $model : null, $customer, $quantity);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureCustomerCanChangeOrder(Order $order): void
    {
        if ($order->state != OrderStateEnum::CREATING) {
            throw ValidationException::withMessages([
                'message' => __('This order has been submitted and cannot be updated'),
            ]);
        }
    }

    protected function findCustomerLine(Order $order, Product $product): ?Transaction
    {
        return $order->transactions()
            ->where('model_type', 'Product')
            ->where('model_id', $product->id)
            ->where('is_gift', false)
            ->first();
    }

    /**
     * A customer changes only product lines of their own basket, never gifts or charges. They may
     * lower a line at any time, and low stock only warns, but raising a line is buying more: the
     * product must be in stock, or offered for pre-order, still for sale to them and within the
     * product's maximum quantity per order. Lines of on demand products, sales
     * platform orders and external shops are left alone, as in SyncBasketLinesWithProductStock.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureCustomerCanChangeLine(Transaction $transaction, mixed $quantity): void
    {
        $order = $transaction->order;
        $this->ensureCustomerCanChangeOrder($order);

        $product = $transaction->model;
        if (!$product instanceof Product || $transaction->is_gift) {
            throw ValidationException::withMessages([
                'message' => __('This line cannot be changed'),
            ]);
        }

        if ($quantity === null
            || (float) $quantity <= (float) $transaction->quantity_ordered
            || $order->platform_order_id
            || $order->shop->type == ShopTypeEnum::EXTERNAL) {
            return;
        }

        if (!$product->is_on_demand && ($product->available_quantity ?? 0) <= 0 && !GetProductPreOrder::make()->type($product)) {
            throw ValidationException::withMessages([
                'message' => __(':product is out of stock', ['product' => $product->code]),
            ]);
        }

        $this->ensureProductIsPurchasableByCustomer($product, $order->customer, $quantity);
    }
}
