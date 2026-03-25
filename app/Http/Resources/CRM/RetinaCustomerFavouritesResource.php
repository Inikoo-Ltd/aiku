<?php

/*
 * author Arya Permana - Kirin
 * created on 14-10-2024-10h-32m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\HasPriceMetrics;
use App\Http\Resources\Traits\HasRetinaCustomerProductData;

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
 * @property mixed $parent_url
 * @property mixed $quantity_ordered
 * @property mixed $canonical_url
 * @property mixed $is_on_demand
 * @property mixed $product_offers_data
 */
class RetinaCustomerFavouritesResource extends JsonResource
{
    use HasSelfCall;
    use HasPriceMetrics;
    use HasRetinaCustomerProductData;

    public function toArray($request): array
    {
        return $this->getCustomerProductData($request);
    }
}
