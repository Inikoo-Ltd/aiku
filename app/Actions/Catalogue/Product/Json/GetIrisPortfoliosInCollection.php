<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Models\Catalogue\Collection;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisPortfoliosInCollection extends IrisAction
{
    public function handle(Customer $customer, Collection $collection): array
    {
        $query = DB::table('portfolios');
        $query->where('customer_id', $customer->id);
        $query->leftJoin('products', function ($join) {
            $join->on('portfolios.item_id', '=', 'products.id');
        })->where('portfolios.item_type', 'Product');
        $query->join('model_has_collections', function ($join) use ($collection) {
            $join->on('products.id', '=', 'model_has_collections.model_id')
                ->where('model_has_collections.model_type', '=', 'Product')
                ->where('model_has_collections.collection_id', '=', $collection->id);
        });



        $query->selectRaw('products.id,array_agg(customer_sales_channel_id) as customer_channels')->groupBy('products.id');

        $portfoliosData = [];
        foreach ($query->get() as $data) {
            // Convert psql array string to a PHP array
            $channels = json_decode(str_replace(['{', '}'], ['[', ']'], $data->customer_channels), true);
            $portfoliosData[$data->id] = $channels;
        }


        return $portfoliosData;
    }


    public function asController(Collection $collection, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle(customer: $request->user()->customer, collection: $collection);
    }

    public function jsonResponse($portfolios): array
    {
        return $portfolios;
    }


}
