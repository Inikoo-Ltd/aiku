<?php

/*
 * author Louis Perez
 * created on 28-05-2026-14h-53m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Catalogue;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Traits\HasPriceMetrics;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property mixed $image_id
 * @property string $code
 * @property string $name
 * @property mixed $available_quantity
 * @property mixed $price
 * @property mixed $state
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $units
 * @property mixed $unit
 * @property mixed $status
 * @property mixed $rrp
 * @property mixed $id
 * @property string $url
 * @property mixed $currency
 * @property mixed $currency_code
 * @property mixed $web_images
 * @property mixed $top_seller
 * @property mixed $group_id
 * @property mixed $organisation_id
 * @property mixed $webpage_id
 * @property mixed $website_id
 * @property mixed $shop_id
 * @property mixed $canonical_url
 * @property mixed $transaction_id
 * @property mixed $is_on_demand
 * @property mixed $is_variant
 */
class IrisProductsInWebpageForWorkshopResource extends JsonResource
{
    use HasSelfCall;
    use HasPriceMetrics;

    public function toArray($request): array
    {
        $oldLuigiIdentity = $this->group_id.':'.$this->organisation_id.':'.$this->shop_id.':'.$this->website_id.':'.$this->webpage_id;

        $url = $this->canonical_url;
        if (!app()->environment('production')) {
            $url = ShowIrisWebpage::make()->getEnvironmentUrl($url);
        }

        [$margin, $rrpPerUnit, $profit, $profitPerUnit, $units, $pricePerUnit] = $this->getPriceMetrics($this->rrp, $this->price, $this->units);


        return [
            'id'              => $this->id,
            'code'            => $this->code,
            'luigi_identity'  => $oldLuigiIdentity,
            'name'            => $this->name,
            'stock'           => $this->available_quantity,
            'price'           => $this->price,
            'price_per_unit'  => $pricePerUnit,
            'margin'          => $margin,
            'profit'          => $profit,
            'profit_per_unit' => $profitPerUnit,
            'rrp'             => $this->rrp,
            'rrp_per_unit'    => $rrpPerUnit,
            'state'           => $this->state,
            'status'          => $this->status,
            'units'           => $units,
            'unit'            => $this->unit,
            'url'             => $url,
            'top_seller'      => $this->top_seller,
            'web_images'      => $this->web_images,
            'is_variant'      => $this->is_variant,
        ];
    }
}
