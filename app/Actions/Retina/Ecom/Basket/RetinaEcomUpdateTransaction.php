<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Basket;

use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithCustomerPurchasableProduct;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class RetinaEcomUpdateTransaction extends RetinaAction
{
    use WithCustomerPurchasableProduct;

    private Order $order;

    public function handle(Transaction $transaction, array $modelData)
    {
        $this->ensureCustomerCanChangeLine($transaction, Arr::get($modelData, 'quantity_ordered'));

        $transaction->order->update([
            'updated_by_customer_at' => now()
        ]);

        return UpdateTransaction::run($transaction, $modelData);
    }


    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        /** @var Transaction $transaction */
        $transaction = $request->route('transaction');
        if ($transaction->customer_id != $request->user()->customer_id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_ordered' => ['sometimes', 'integer', 'min:0', 'max:999999'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->order->state != OrderStateEnum::CREATING) {
            throw ValidationException::withMessages([
                'message' => __('This order has been submitted and cannot be updated'),
            ]);
        }
    }

    public function action(Transaction $transaction, Customer $customer, array $modelData): Transaction
    {
        $this->asAction    = true;
        $this->order       = $transaction->order;
        $this->initialisationActions($customer, $modelData);

        return $this->handle($transaction, $this->validatedData);
    }

    public function asController(Transaction $transaction, ActionRequest $request): void
    {
        $this->order       = $transaction->order;
        $this->initialisation($request);

        $this->handle($transaction, $this->validatedData);
    }
}
