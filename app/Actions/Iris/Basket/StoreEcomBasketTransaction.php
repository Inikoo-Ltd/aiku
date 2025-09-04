<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 09 May 2025 13:37:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Basket;

use App\Actions\IrisAction;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Ordering\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreEcomBasketTransaction extends IrisAction
{
    public function handle(Customer $customer, Product $product, array $modelData): Transaction
    {
        $order = $customer->orderInBasket;

        if (!$order) {
            $order = StoreEcomBasket::make()->action($customer);
        }

        $historicAsset = $product->currentHistoricProduct;

        if($order) {
            $existingTransaction = $order->transactions()->where('historic_asset_id', $historicAsset->id)->first();
            if($existingTransaction) {
                 throw ValidationException::withMessages(
                    [
                        'message' => [
                            'transaction' => 'Product already exist in basket',
                        ]
                    ]
                );
            }
            
        }

        $transaction = StoreTransaction::make()->action($order, $historicAsset, [
            'quantity_ordered' => Arr::get($modelData, 'quantity')
        ]);

        return $transaction;
    }

    public function rules(): array
    {
        return [
            'quantity'          => ['required', 'numeric', 'min:0'],
        ];
    }

    public function asController(Product $product, ActionRequest $request): Transaction
    {
        $customer = $request->user()->customer;
        $this->initialisation($request);

        return $this->handle($customer, $product, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function jsonResponse(Transaction $transaction): array
    {
        return [
            'transaction_id'    => $transaction->id,
            'quantity_ordered'  => (int) $transaction->quantity_ordered
        ];
    }
}
