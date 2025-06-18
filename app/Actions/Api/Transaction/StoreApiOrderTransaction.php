<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-11h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Transaction;

use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Http\Resources\Api\TransactionResource;
use App\Models\Dropshipping\Portfolio;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreApiOrderTransaction
{
    use AsAction;
    use WithAttributes;

    public function handle(Order $order, Portfolio $portfolio, array $modelData): Transaction
    {
        $transaction = StoreTransaction::make()->action($order, $portfolio->item->historicAsset, $modelData);

        return $transaction;
    }

    public function rules(): array
    {
        $rules = [
            'quantity_ordered'    => ['required', 'numeric', 'min:0'],
        ];

        return $rules;
    }

    public function asController(Order $order, Portfolio $portfolio, ActionRequest $request): Transaction
    {
        $this->fillFromRequest($request);
        $validatedData = $this->validateAttributes();

        return $this->handle($order, $portfolio, $validatedData);
    }

    public function jsonResponse(Transaction $transaction)
    {
        return TransactionResource::make($transaction)
            ->additional([
                'message' => __('Transaction created successfully'),
            ]);
    }
}
