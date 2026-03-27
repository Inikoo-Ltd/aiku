<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Dropshipping;

use App\Helpers\NaturalLanguage;
use Illuminate\Http\Resources\Json\JsonResource;

class DropshippingBundlesResource extends JsonResource
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
            'full_size_image'       => $fullSizeImage
        ];
    }
}
