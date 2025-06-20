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

class GetIrisPortfoliosInProductCategory extends IrisAction
{
    public function handle(Customer $customer, ProductCategory $productCategory): array
    {
        $query = DB::table('portfolios');
        $query->where('customer_id', $customer->id);
        $query->leftJoin('products', function ($join) {
            $join->on('portfolios.item_id', '=', 'products.id');
        })->where('portfolios.item_type', 'Product');


        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $query->where('products.department_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $query->where('products.family_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $query->where('products.sub_department_id', $productCategory->id);
        }
        $query->selectRaw('products.id,array_agg(customer_sales_channel_id) as customer_channels')->groupBy('products.id');

        $portfoliosData = [];
        foreach ($query->get() as $data) {
            // Convert PostgreSQL array string to PHP array
            $channels = json_decode(str_replace(['{', '}'], ['[', ']'], $data->customer_channels), true);
            $portfoliosData[$data->id] = $channels;
        }
        

        return $portfoliosData;
    }


    public function asController(ProductCategory $productCategory, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle(customer: $request->user()->customer, productCategory: $productCategory);
    }

    public function jsonResponse($portfolios): array
    {
        return $portfolios;
    }


}
