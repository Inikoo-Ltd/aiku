<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sept 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\Orders;

use App\Actions\Retina\Dropshipping\Orders\Transaction\StoreRetinaEcomBasketTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\InteractsWithOrderInBasket;
use App\Actions\Traits\WithCustomerPurchasableProduct;
use App\Actions\Traits\WithRetinaCustomerOwnedRouteModels;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\ActionRequest;

/**
 * Puts the products of a past order back in the basket, in the quantities ordered then.
 *
 * A product already in the basket is raised to the old quantity, never added on top, so pressing the
 * button twice does not double the order. Products no longer for sale, or out of stock, are skipped
 * and reported back; a quantity above what is in stock is lowered to the stock.
 */
class RepeatRetinaEcomOrder extends RetinaAction
{
    use WithRetinaCustomerOwnedRouteModels;
    use WithCustomerPurchasableProduct;
    use InteractsWithOrderInBasket;

    /**
     * @return array{added: int, skipped: array<int, array{code: string, name: string}>}
     * @throws \Throwable
     */
    public function handle(Customer $customer, Order $order): array
    {
        $basket         = $this->getOrderInBasket($customer);
        $basketQuantity = $basket
            ? $basket->transactions()->where('model_type', 'Product')->where('is_gift', false)->pluck('quantity_ordered', 'model_id')
            : collect();

        $added   = 0;
        $skipped = [];

        $order->transactions()
            ->where('model_type', 'Product')
            ->where('is_gift', false)
            ->with('model')
            ->get()
            ->groupBy('model_id')
            ->each(function ($lines) use ($customer, $basketQuantity, &$added, &$skipped) {
                /** @var Transaction $line */
                $line    = $lines->first();
                $product = $line->model;

                if (!$product instanceof Product) {
                    return;
                }

                $quantity = (int) ceil($lines->sum('quantity_ordered'));
                if (!$product->is_on_demand) {
                    $quantity = min($quantity, (int) $product->available_quantity);
                }

                if ($quantity <= 0 || !$this->isProductPurchasableByCustomer($product, $customer)) {
                    $skipped[] = ['code' => $product->code, 'name' => $product->name];

                    return;
                }

                if ((float) $basketQuantity->get($product->id, 0) >= $quantity) {
                    $added++;

                    return;
                }

                StoreRetinaEcomBasketTransaction::make()->handle($customer, $product, ['quantity' => $quantity]);
                $customer->refresh();
                $added++;
            });

        return [
            'added'   => $added,
            'skipped' => $skipped,
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || $this->retinaCustomerOwnsRouteModels($request);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $order);
    }
}
