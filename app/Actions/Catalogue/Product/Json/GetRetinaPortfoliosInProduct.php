<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\RetinaAction;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetRetinaPortfoliosInProduct extends RetinaAction
{
    public function handle(Customer $customer, Product $product): array
    {
        return DB::table('portfolios')
            ->where('customer_id', $customer->id)
            ->where('item_type', 'Product')
            ->where('item_id', $product->id)
            ->pluck('customer_sales_channel_id')
            ->all();
    }


    public function asController(Product $product, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle(customer: $this->customer, product: $product);
    }

    public function jsonResponse($portfolios): array
    {
        return $portfolios;
    }


}
