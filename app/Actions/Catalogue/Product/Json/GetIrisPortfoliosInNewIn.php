<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 09 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\Product\ProductBadgeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisPortfoliosInNewIn extends IrisAction
{
    /**
     * @return array<int, array<int, int>> the sales channels of each NEW IN product already in the customer's portfolio
     */
    public function handle(Shop $shop): array
    {
        $customer = request()->user()?->customer;
        if (!$customer) {
            return [];
        }

        $portfolios = DB::table('portfolios')
            ->join('products', 'portfolios.item_id', '=', 'products.id')
            ->where('portfolios.item_type', 'Product')
            ->where('portfolios.customer_id', $customer->id)
            ->where('products.shop_id', $shop->id)
            ->where('products.first_available_at', '>', ProductBadgeEnum::newInSince())
            ->selectRaw('products.id, array_agg(customer_sales_channel_id) as customer_channels')
            ->groupBy('products.id')
            ->get();

        $portfoliosData = [];
        foreach ($portfolios as $portfolio) {
            $portfoliosData[$portfolio->id] = json_decode(str_replace(['{', '}'], ['[', ']'], $portfolio->customer_channels), true);
        }

        return $portfoliosData;
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->shop);
    }

    public function jsonResponse(array $portfolios): array
    {
        return $portfolios;
    }
}
