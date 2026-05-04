<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 May 2026 15:24:42 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dropshipping;

use App\Helpers\NaturalLanguage;
use Illuminate\Http\Resources\Json\JsonResource;

//todo: Delete this and use DropshippingBundlesResource
class DropshippingJsonBundlesResource extends JsonResource
{
    public function toArray($request): array
    {
        $quantity         = $this->bundleable->available_quantity;
        $weight           = $this->bundleable->gross_weight;
        $marketing_weight = $this->bundleable->marketing_weight;
        $dimension        = NaturalLanguage::make()->dimensions(json_encode($this->bundleable->marketing_dimensions));
        $price            = $this->bundleable->price;
        $image            = $this->bundleable->imageSources(64, 64);
        $fullSizeImage    = $this->bundleable->imageSources();

        return [
            'id'                    => $this->id,
            'product_id'            => $this->product_id,
            'code'                  => $this->product_code,
            'name'                  => $this->product_name,
            'description'           => $this->product_description,
            'currency_code'         => $this->bundleable?->currency?->code,
            'quantity_left'         => $quantity,
            'weight'                => $weight,
            'marketing_weight'      => $marketing_weight,
            'dimension'             => $dimension,
            'price'                 => $price,
            'price_include_vat'     => $price,
            'selling_price'         => $this->bundleable->rrp,
            'customer_price'        => $this->bundleable->rrp,
            'status'                => $this->status,
            'image'                 => $image,
            'full_size_image'       => $fullSizeImage,
            'has_valid_platform_product_id'          => $this->has_valid_platform_product_id,
            'exist_in_platform'                      => $this->exist_in_platform,
            'platform_status'                        => $this->platform_status,
        ];
    }
}
