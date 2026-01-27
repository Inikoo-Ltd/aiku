<?php

/*
 * author Louis Perez
 * created on 20-11-2025-15h-21m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Iris\Catalogue;

use App\Actions\IrisAction;
use App\Http\Resources\Traits\HasPriceMetrics;
use App\Models\Catalogue\Product;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetProductDetail extends IrisAction
{
    use HasPriceMetrics;

    public function handle(Product $product): Product
    {
        return $product;
    }

    public function jsonResponse(Product $product, ActionRequest $request): array
    {

        $bestPercentageOff            = Arr::get($product->offers_data, 'best_percentage_off.percentage_off', 0);
        $bestPercentageOffOfferFactor = 1 - (float)$bestPercentageOff;

        /** @noinspection PhpUnusedLocalVariableInspection */
        [$marginDiscounted, $rrpPerUnitDiscounted, $profitDiscounted, $profitPerUnitDiscounted, $unitsDiscounted, $pricePerUnitDiscounted] = $this->getPriceMetrics($product->rrp, $bestPercentageOffOfferFactor * $product->price, $product->units);

        return [
            'discounted_price'           => round($product->price * $bestPercentageOffOfferFactor, 2),
            'discounted_price_per_unit'  => $pricePerUnitDiscounted,
            'discounted_profit'          => $profitDiscounted,
            'discounted_profit_per_unit' => $profitPerUnitDiscounted,
            'discounted_margin'          => $marginDiscounted,
            'discounted_percentage'      => percentage($bestPercentageOff, 1),
            'offers_data'                => $product->offers_data,

        ];

    }

    public function asController(Product $product, ActionRequest $request): Product
    {
        $this->initialisation($request);
        return $this->handle($product);
    }

}
