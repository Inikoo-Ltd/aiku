<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 09 May 2025 13:37:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Basket;

use App\Actions\IrisAction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Models\Ordering\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateEcomBasketTransaction extends IrisAction
{
    public function handle(Transaction $transaction, array $modelData): Transaction
    {
        return UpdateTransaction::make()->action($transaction, [
            'quantity_ordered' => Arr::get($modelData, 'quantity')
        ]);
    }

    public function rules(): array
    {
        return [
            'quantity'          => ['required', 'numeric', 'min:0'],
        ];
    }

    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->initialisation($request);

        return $this->handle($transaction, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
