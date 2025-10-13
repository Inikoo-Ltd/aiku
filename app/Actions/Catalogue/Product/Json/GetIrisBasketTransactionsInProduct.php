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
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisBasketTransactionsInProduct extends IrisAction
{
    public function handle(Customer $customer, Product $product): array
    {
        $basket = $customer->orderInBasket;
        $query = DB::table('products');
        if ($basket) {
            $query->leftjoin('transactions', function ($join) use ($basket) {
                $join->on('transactions.model_id', '=', 'products.id')
                    ->where('transactions.model_type', '=', 'Product')
                    ->where('transactions.order_id', '=', $basket->id)
                    ->whereNull('transactions.deleted_at');
            });
        }
        $query->where('products.id', '=', $product->id);
        $query->selectRaw('products.id,array_agg(transactions.quantity_ordered) as quantity_ordered')->groupBy('products.id');

        $productsData = [];
        foreach ($query->get() as $data) {
            $quantityOrdered = json_decode(str_replace(['{', '}'], ['', ''], $data->quantity_ordered), true);
            $productsData[$data->id] = [
                'is_favourite' => $data->is_favourite,
                'quantity_ordered' => $quantityOrdered,
                'quantity_ordered_new' => 0
            ];
        }


        return $productsData;
    }


    public function asController(Product $product, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle(customer: $request->user()->customer, product: $product);
    }

    public function jsonResponse($products): array
    {
        return $products;
    }


}
