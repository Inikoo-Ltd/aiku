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
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaTransaction extends RetinaAction
{
    use WithActionUpdate;

    public function handle(Transaction $transaction, array $modelData): Transaction
    {
        if (Arr::get($modelData, 'quantity_ordered', 0) === 0) {
            $transaction->forceDelete();

            return $transaction;
        }

        return UpdateTransaction::make()->action($transaction, $modelData);
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
        $this->initialisation($request);

        return $this->handle($transaction, $this->validatedData);
    }
}
