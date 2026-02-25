<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 15:08:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\HasPriceMetrics;
use Illuminate\Support\Arr;

/**
 * @property mixed $slug
 * @property mixed $image_id
 * @property mixed $code
 * @property mixed $name
 * @property mixed $available_quantity
 * @property mixed $price
 * @property mixed $state
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $units
 * @property mixed $unit
 * @property mixed $status
 * @property mixed $rrp
 * @property mixed $description
 * @property mixed $web_images
 */
class ProductsWebpageResource extends JsonResource
{
    use HasSelfCall;
    use HasPriceMetrics;

    public function toArray($request): array
    {
        if (!is_array($this->web_images)) {
            $webImages = json_decode(trim($this->web_images, '"'), true) ?? [];
        } else {
            $webImages = $this->web_images;
        }

        $productOffersData = is_string($this->offers_data)
            ? json_decode($this->offers_data, true)
            : $this->offers_data;
        $bestPercentageOff            = Arr::get($productOffersData, 'best_percentage_off.percentage_off', 0);
        $bestPercentageOffOfferFactor = 1 - (float)$bestPercentageOff;

        [$margin, $rrpPerUnit, $profit, $profitPerUnit, $units, $pricePerUnit] = $this->getPriceMetrics($this->rrp, $this->price, $this->units);
        [$marginDiscounted, $rrpPerUnitDiscounted, $profitDiscounted, $profitPerUnitDiscounted, $unitsDiscounted, $pricePerUnitDiscounted] = $this->getPriceMetrics($this->rrp, $bestPercentageOffOfferFactor * $this->price, $this->units);


        [$margin, $rrpPerUnit, $profit, $profitPerUnit, $units, $pricePerUnit] = $this->getPriceMetrics($this->rrp, $this->price, $this->units);

        return [
            'id'                            => $this->id,
            'code'                          => $this->code,
            'name'                          => $this->name,
            'stock'                         => $this->available_quantity,
            'price'                         => $this->price,
            'price_per_unit'                => $pricePerUnit,
            'profit'                        => $profit,
            'profit_per_unit'               => $profitPerUnit,
            'rrp'                           => $this->rrp,
            'rrp_per_unit'                  => $rrpPerUnit,
            'margin'                        => $margin,
            'web_images'                    => $this->web_images,
            'url'                           => $this->url,
            'unit'                          => $this->unit,
            'units'                         => $units,
            'offers_data'                   => $this->offers_data,
            'discounted_price'              => $bestPercentageOff ? round($this->price * $bestPercentageOffOfferFactor, 2) : null,
            'discounted_price_per_unit'     => $pricePerUnitDiscounted,
            'discounted_profit'             => $profitDiscounted,
            'discounted_profit_per_unit'    => $profitPerUnitDiscounted,
            'discounted_margin'             => $marginDiscounted,
            'discounted_percentage'         => percentage($bestPercentageOff, 1),
            'product_offers_data'           => $productOffersData,
        ];
    }
}
