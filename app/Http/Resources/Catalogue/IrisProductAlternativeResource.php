<?php

namespace App\Http\Resources\Catalogue;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Traits\HasPriceMetrics;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $id
 * @property mixed $code
 * @property mixed $name
 * @property mixed $available_quantity
 * @property mixed $price
 * @property mixed $rrp
 * @property mixed $units
 * @property mixed $unit
 * @property mixed $web_images
 * @property mixed $url
 * @property mixed $canonical_url
 * @property mixed $webpage_id
 * @property mixed $offers_data
 * @property mixed $product_offers_data
 * @property mixed $alternative_score
 */
class IrisProductAlternativeResource extends JsonResource
{
    use HasSelfCall;
    use HasPriceMetrics;

    public function toArray($request): array
    {
        $productOffersData = json_decode($this->product_offers_data, true);

        $bestPercentageOff            = Arr::get($productOffersData, 'best_percentage_off.percentage_off', 0);
        $bestPercentageOffOfferFactor = 1 - (float) $bestPercentageOff;

        [$margin, $rrpPerUnit, $profit, $profitPerUnit, $units, $pricePerUnit] = $this->getPriceMetrics($this->rrp, $this->price, $this->units);
        [$marginDiscounted, , $profitDiscounted, $profitPerUnitDiscounted, , $pricePerUnitDiscounted] = $this->getPriceMetrics($this->rrp, $bestPercentageOffOfferFactor * $this->price, $this->units);

        $canonicalUrl = $this->canonical_url;
        if ($canonicalUrl && !app()->environment('production')) {
            $canonicalUrl = ShowIrisWebpage::make()->getEnvironmentUrl($canonicalUrl);
        }

        return [
            'id'                         => $this->id,
            'code'                       => $this->code,
            'name'                       => $this->name,
            'stock'                      => $this->available_quantity,
            'price'                      => $this->price,
            'price_per_unit'             => $pricePerUnit,
            'profit'                     => $profit,
            'profit_per_unit'            => $profitPerUnit,
            'rrp'                        => $this->rrp,
            'rrp_per_unit'               => $rrpPerUnit,
            'margin'                     => $margin,
            'web_images'                 => $this->web_images,
            // 'url'                        => $this->url,
            'url'                        => $canonicalUrl ? $canonicalUrl : $this->url,
            'canonical_url'              => $canonicalUrl,
            'webpage_id'                 => $this->webpage_id,
            'unit'                       => $this->unit,
            'units'                      => $units,
            'offers_data'                => $this->offers_data,
            'discounted_price'           => $bestPercentageOff ? round($this->price * $bestPercentageOffOfferFactor, 2) : null,
            'discounted_price_per_unit'  => $pricePerUnitDiscounted,
            'discounted_profit'          => $profitDiscounted,
            'discounted_profit_per_unit' => $profitPerUnitDiscounted,
            'discounted_margin'          => $marginDiscounted,
            'discounted_percentage'      => percentage($bestPercentageOff, 1),
            'product_offers_data'        => $productOffersData,
            'alternative_score'          => round((float) $this->alternative_score, 4),
        ];
    }
}
