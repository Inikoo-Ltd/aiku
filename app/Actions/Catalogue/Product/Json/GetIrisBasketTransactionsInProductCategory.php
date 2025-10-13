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
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisBasketTransactionsInProductCategory extends IrisAction
{
    public function handle(Customer $customer, ProductCategory $productCategory): array
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
            $query->selectRaw('products.id,array_agg(transactions.quantity_ordered) as quantity_ordered')->groupBy('products.id');

        } else {
            $query->selectRaw('products.id')->groupBy('products.id');

        }


        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $query->where('products.department_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $query->where('products.family_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $query->where('products.sub_department_id', $productCategory->id);
        }

        $productsData = [];
        foreach ($query->get() as $data) {
            if ($basket) {
                $quantityOrdered = json_decode(str_replace(['{', '}'], ['', ''], $data->quantity_ordered), true);
            } else {
                $quantityOrdered = null;
            }
            $productsData[$data->id] = [
                'quantity_ordered' => $quantityOrdered,
                'quantity_ordered_new' => 0
            ];
        }


        return $productsData;
    }


    public function asController(ProductCategory $productCategory, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle(customer: $request->user()->customer, productCategory: $productCategory);
    }

    public function jsonResponse($products): array
    {
        return $products;
    }


}
