<?php

/*
 * author Louis Perez
 * created on 03-03-2026-10h-27m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisBasketTransactions extends IrisAction
{
    public function handle(Customer $customer): array
    {
        $basket = $customer->orderInBasket;
        
        $query = DB::table('transactions')
            ->where('transactions.order_id', $basket->id)
            ->where('transactions.model_type', class_basename(Product::class))
            ->leftJoin('products', 'products.id', 'transactions.model_id')
            ->whereNull('transactions.deleted_at');
        
        $query->select([
            'products.id as product_id',
            'transactions.id as transaction_id',
            'transactions.quantity_ordered',
        ]);

        return $query
            ->get()
            ->keyBy('product_id')
            ->map(function($data) { 
                return [
                    'transaction_id' => $data->transaction_id,
                    'quantity_ordered' => $data->quantity_ordered,
                    'quantity_ordered_new' => $data->quantity_ordered,
                ];
            })->toArray();
    }


    public function asController(ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle(customer: $request->user()->customer);
    }

    public function jsonResponse($products): array
    {
        return $products;
    }


}
