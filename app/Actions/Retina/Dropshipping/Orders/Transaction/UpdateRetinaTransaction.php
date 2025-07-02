<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 09 May 2025 13:37:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders\Transaction;

use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaTransaction extends RetinaAction
{
    use WithActionUpdate;

    private Order $order;

    public function handle(Transaction $transaction, array $modelData): Transaction
    {
        if (Arr::get($modelData, 'quantity_ordered', 0) === 0) {
            $transaction->forceDelete();

            return $transaction;
        }

        return UpdateTransaction::make()->action($transaction, $modelData);
    }

    public function prepareForValidation()
    {
        if ($this->order->state != OrderStateEnum::CREATING) {
            throw ValidationException::withMessages([
                'messages' => __('This order has been submitted and cannot be updated'),
            ]);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        $transaction = $request->route('transaction');
        if ($transaction->customer_id !== $this->customer->id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_ordered' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->order = $transaction->order;
        $this->initialisation($request);

        return $this->handle($transaction, $this->validatedData);
    }
}
