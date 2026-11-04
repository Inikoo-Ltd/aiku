<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 09 May 2025 13:37:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Basket;

use App\Actions\IrisAction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\Traits\WithCustomerPurchasableProduct;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateEcomBasketTransaction extends IrisAction
{
    use WithCustomerPurchasableProduct;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Transaction $transaction, array $modelData): Transaction
    {
        $this->ensureCustomerCanChangeLine($transaction, Arr::get($modelData, 'quantity_ordered'));

        $transaction->order->update([
            'updated_by_customer_at' => now()
        ]);
        return UpdateTransaction::make()->action($transaction, [
            'quantity_ordered' => Arr::get($modelData, 'quantity_ordered')
        ]);
    }

    public function rules(): array
    {
        return [
            'quantity_ordered'          => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * A product page or basket can still hold a line that was removed elsewhere (the side
     * basket, another tab, an earlier request). Setting it to 0 is then already done, and any
     * other quantity goes to the customer's current basket as a fresh add.
     *
     * @throws \Throwable
     */
    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $customer = $request->user()?->customer;
        $order    = $transaction->order()->withTrashed()->first();

        if (!$customer || $order?->customer_id != $customer->id) {
            abort(404);
        }

        if (!$transaction->trashed() && $order->state != OrderStateEnum::CREATING) {
            abort(422, 'Order can not be modified after submission');
        }

        $this->initialisation($request);

        if ($transaction->trashed()) {
            $quantity = (int)Arr::get($this->validatedData, 'quantity_ordered');
            $product  = $transaction->model;

            return $quantity === 0 || !$product instanceof Product
                ? $transaction
                : StoreEcomBasketTransaction::make()->handle($customer, $product, ['quantity' => $quantity]);
        }

        return $this->handle($transaction, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function jsonResponse(Transaction $transaction): array
    {
        $product = $transaction->model;

        return [
            'transaction_id'    => $transaction->trashed() ? null : $transaction->id,
            'quantity_ordered'  => $transaction->trashed() ? 0 : (int)$transaction->quantity_ordered,
            'department_id'     => $product?->department_id,
            'sub_department_id' => $product?->sub_department_id,
            'family_id'         => $product?->family_id,
            'is_golden_product' => (bool)$product?->is_golden_product,
        ];
    }
}
