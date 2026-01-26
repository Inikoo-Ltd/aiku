<?php

/*
 * author Louis Perez
 * created on 09-01-2026-13h-37m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Iris\Basket;

use App\Actions\IrisAction;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\ActionRequest;

class GetIrisBasketTransactionProductData extends IrisAction
{
    public function handle(Transaction $transaction): array
    {
        /** @var Product $product */
        $product = $transaction->model;

        $offerNetAmountPerQuantity = (int)$transaction->quantity_ordered ? ($transaction->net_amount / ((int)$transaction->quantity_ordered ?? null)) : null;

        return [
            'quantity_ordered_new'          => $transaction->quantity_ordered,
            'transaction_id'                => $transaction->id,
            'quantity_ordered'              => $transaction->quantity_ordered,
            'stock'                         => $product->available_quantity,
            'offers_data'                   => $product->offers_data,
            'offer_net_amount_per_quantity' => $offerNetAmountPerQuantity,
            'offer_price_per_unit'          => $offerNetAmountPerQuantity ? $offerNetAmountPerQuantity / $product->units : null,
        ];
    }


    public function asController(Transaction $transaction, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($transaction);
    }


}
