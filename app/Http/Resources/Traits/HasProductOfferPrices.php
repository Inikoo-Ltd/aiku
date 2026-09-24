<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Traits;

use App\Enums\Catalogue\Product\ProductStatusEnum;
use Illuminate\Support\Arr;

trait HasProductOfferPrices
{
    /**
     * Shop-wide offer prices of a product, the same for every customer.
     *
     * @return array<string, mixed>
     */
    protected function getProductOfferPrices(): array
    {
        $productOffersData = is_string($this->product_offers_data) ? json_decode($this->product_offers_data, true) : $this->product_offers_data;

        $bestPercentageOff            = Arr::get($productOffersData, 'best_percentage_off.percentage_off', 0);
        $bestPercentageOffOfferFactor = 1 - (float)$bestPercentageOff;

        [$marginDiscounted, , $profitDiscounted, $profitPerUnitDiscounted, , $pricePerUnitDiscounted] = $this->getPriceMetrics($this->rrp, $bestPercentageOffOfferFactor * $this->price, $this->units);

        return [
            'family_id'                  => $this->family_id,
            'is_coming_soon'             => $this->status === ProductStatusEnum::COMING_SOON,
            'is_golden_product'          => (bool)$this->is_golden_product,
            'variant'                    => $this->variant_id,
            'product_offers_data'        => $productOffersData,
            'discounted_price'           => round($this->price * $bestPercentageOffOfferFactor, 2),
            'discounted_price_per_unit'  => $pricePerUnitDiscounted,
            'discounted_profit'          => $profitDiscounted,
            'discounted_profit_per_unit' => $profitPerUnitDiscounted,
            'discounted_margin'          => $marginDiscounted,
            'discounted_percentage'      => percentage($bestPercentageOff, 1),
            'step_discount'              => $this->getStepDiscount(),
        ];
    }

    /**
     * @return array{label: mixed, steps: array<int, array<string, mixed>>}|null
     */
    protected function getStepDiscount(): ?array
    {
        $stepDiscountData = is_string($this->step_discount_data) ? json_decode($this->step_discount_data, true) : $this->step_discount_data;

        $steps = Arr::get($stepDiscountData, 'steps', []);

        if (empty($steps)) {
            return null;
        }

        $tiers = collect($steps)
            ->sortBy('min_quantity')
            ->map(function (array $step) {
                $percentageOff   = (float)Arr::get($step, 'percentage_off', 0);
                $discountedPrice = round($this->price * (1 - $percentageOff), 2);

                [, , , , , $pricePerUnit] = $this->getPriceMetrics($this->rrp, $discountedPrice, $this->units);

                return [
                    'min_quantity'         => (int)Arr::get($step, 'min_quantity', 1),
                    'percentage_off'       => $percentageOff,
                    'percentage_off_label' => percentage($percentageOff, 1),
                    'price'                => $discountedPrice,
                    'price_per_unit'       => $pricePerUnit,
                    'is_popular'           => (bool)Arr::get($step, 'is_popular', false),
                ];
            })
            ->values()
            ->all();

        return [
            'label' => Arr::get($stepDiscountData, 'label'),
            'steps' => $tiers,
        ];
    }
}
