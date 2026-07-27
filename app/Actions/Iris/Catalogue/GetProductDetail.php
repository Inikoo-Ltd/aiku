<?php

/*
 * author Louis Perez
 * created on 20-11-2025-15h-21m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Iris\Catalogue;

use App\Actions\IrisAction;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\Offer\OfferTypeEnum;
use App\Http\Resources\Traits\HasPriceMetrics;
use App\Models\Catalogue\Product;
use App\Models\Discounts\Offer;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetProductDetail extends IrisAction
{
    use HasPriceMetrics;

    public function handle(Product $product): Product
    {
        return $product;
    }

    private function getStepDiscount(Product $product): ?array
    {
        $offer = Offer::where('trigger_type', 'Product')
            ->where('trigger_id', $product->id)
            ->where('type', OfferTypeEnum::PRODUCT_QUANTITY_ORDERED->value)
            ->where('state', OfferStateEnum::ACTIVE->value)
            ->where('status', true)
            ->first();

        if (!$offer) {
            return null;
        }

        $allowance = $offer->offerAllowances()->where('status', true)->first();
        $steps     = Arr::get($allowance?->data, 'steps', []);

        if (empty($steps)) {
            return null;
        }

        $tiers = collect($steps)
            ->sortBy('min_quantity')
            ->map(function (array $step) use ($product) {
                $percentageOff   = (float)Arr::get($step, 'percentage_off', 0);
                $discountedPrice = round($product->price * (1 - $percentageOff), 2);

                [, , , , , $pricePerUnit] = $this->getPriceMetrics($product->rrp, $discountedPrice, $product->units);

                return [
                    'min_quantity'         => (int)Arr::get($step, 'min_quantity', 1),
                    'percentage_off'       => $percentageOff,
                    'percentage_off_label' => percentage($percentageOff, 1),
                    'price'                => $discountedPrice,
                    'price_per_unit'       => $pricePerUnit,
                ];
            })
            ->values()
            ->all();

        return [
            'label' => $offer->label ?? $offer->name,
            'steps' => $tiers,
        ];
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
            'step_discount'              => $this->getStepDiscount($product),

        ];

    }

    public function asController(Product $product, ActionRequest $request): Product
    {
        $this->initialisation($request);
        return $this->handle($product);
    }

}
