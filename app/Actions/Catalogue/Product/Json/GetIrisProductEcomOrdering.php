<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Models\Catalogue\Product;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisProductEcomOrdering extends IrisAction
{
    public function handle(Product $product): array
    {
        $response = [];
        $customer = request()->user()?->customer;
        if ($customer) {
            $favourite           = $customer->favourites()->where('product_id', $product->id)->whereNull('unfavourited_at')->first();
            $backInStockReminder = $customer->BackInStockReminder()->where('product_id', $product->id)->first();
            $back_in_stock       = (bool)$backInStockReminder;
            $back_in_stock_id    = $backInStockReminder?->id;
            $basket              = $customer->orderInBasket;
            $quantityOrdered     = null;
            $transactionId       = null;
            if ($basket) {
                $transaction = DB::table('transactions')->where('order_id', $basket->id)
                    ->where('model_id', $product->id)->where('model_type', 'Product')
                    ->whereNull('deleted_at')
                    ->select('id', 'quantity_ordered')
                    ->first();
                if ($transaction) {
                    $quantityOrdered = $transaction->quantity_ordered;
                    $transactionId   = $transaction->id;
                }
            }

            $response = [
                'is_favourite'        => (bool)$favourite,
                'back_in_stock'    => $back_in_stock,
                'back_in_stock_id' => $back_in_stock_id,
                'quantity_ordered'  => $quantityOrdered,
                'transaction_id'    => $transactionId,
                'quantity_ordered_new'  => $quantityOrdered ? (int) $quantityOrdered : null,
            ];
        }

        return $response;
    }


    public function asController(Product $product, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($product);
    }

    public function jsonResponse(array $products): array|\Illuminate\Http\Resources\Json\JsonResource
    {
        return $products;
    }

}
