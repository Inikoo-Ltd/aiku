<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisBasketTransactionsInCollection extends IrisAction
{
    public function handle(Customer $customer, Collection $collection): array
    {
        $basket = $customer->orderInBasket;
        $query = DB::table('products');

        $query->join('collection_has_models', function ($join) {
            $join->on('products.id', '=', 'collection_has_models.model_id')
                ->where('collection_has_models.model_type', '=', 'Product');

        });
        $query->where('collection_has_models.collection_id', '=', $collection->id);

        if ($basket) {
            $query->leftjoin('transactions', function ($join) use ($basket) {
                $join->on('transactions.model_id', '=', 'products.id')
                    ->where('transactions.model_type', '=', 'Product')
                    ->where('transactions.order_id', '=', $basket->id)
                    ->whereNull('transactions.deleted_at');
            });
        }
        $query->selectRaw('products.id,array_agg(transactions.quantity_ordered) as quantity_ordered')->groupBy('products.id');

        $productsData = [];
        foreach ($query->get() as $data) {
            $quantityOrdered = json_decode(str_replace(['{', '}'], ['', ''], $data->quantity_ordered), true);
            $productsData[$data->id] = [
                'quantity_ordered' => $quantityOrdered,
                'quantity_ordered_new' => 0
            ];
        }


        return $productsData;
    }


    public function asController(Collection $collection, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle(customer: $request->user()->customer, collection: $collection);
    }

    public function jsonResponse($products): array
    {
        return $products;
    }


}
