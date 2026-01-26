<?php

/*
 * author Louis Perez
 * created on 23-01-2026-08h-54m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\HasPriceMetrics;

class IrisLuigiBoxRecommendationResource extends JsonResource
{
    use HasSelfCall;
    use HasPriceMetrics;

    public function toArray($request): array
    {

        [$margin, $rrpPerUnit, $profit, $profitPerUnit, $units, $pricePerUnit] = $this->getPriceMetrics($this->rrp, $this->price, $this->units);

        return [
            'id'                   => $this->id,
            'code'                 => $this->code,
            'name'                 => $this->name,
            'stock'                => $this->available_quantity,
            'price'                => $this->price,
            'price_per_unit'       => $pricePerUnit,
            'profit'               => $profit,
            'profit_per_unit'      => $profitPerUnit,
            'rrp'                  => $this->rrp,
            'rrp_per_unit'         => $rrpPerUnit,
            'margin'               => $margin,
            'web_images'           => $this->web_images,
            'url'                  => $this->url,
            'unit'                 => $this->unit,
            'units'                => $units,

            // TODO: make below to similar like IrisAuthenticatedProductsInWebpageResource
            // 'offers_data'                   => $this->offers_data,
            // 'discounted_price'           => round($this->price * $bestPercentageOffOfferFactor, 2),
            // 'discounted_price_per_unit'  => $pricePerUnitDiscounted,
            // 'discounted_profit'          => $profitDiscounted,
            // 'discounted_profit_per_unit' => $profitPerUnitDiscounted,
            // 'discounted_margin'          => $marginDiscounted,
            // 'discounted_percentage'      => percentage($bestPercentageOff, 1),
        ];
    }


}
