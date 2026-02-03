<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Apr 2023 15:23:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Actions\Discounts\Offer\UpdateProductCategoryOffersData;
use App\Models\Catalogue\ProductCategory;
use App\Models\Discounts\Offer;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $shop_id
 * @property int $offer_campaign_id
 * @property string $slug
 * @property string $code
 * @property string $data
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 *
 */
class OfferResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Offer $offer */
        $offer = $this->resource;

        preg_match('/percentage_off:([0-9]*\.?[0-9]+)/', $this->allowance_signature, $matches);
        $percentage_off = isset($matches[1]) ? $matches[1] : null;

        preg_match('/^all_products_in_product_category(?::(\d+))?:/', $this->allowance_signature, $m);
        $productCategory = isset($m[1]) ? ProductCategory::find($m[1]) : null;


        $basicOfferData = UpdateProductCategoryOffersData::make()->getBasicOfferData($offer);

        $customOfferData = [
            'shop_id'           => $this->shop_id,
            'offer_campaign_id' => $this->offer_campaign_id,
            'slug'              => $this->slug,
            'type'              => $this->type,
            'code'              => $this->code,
            'name'              => $this->name,
            'data'              => $this->data,
            'trigger_type'              => $this->trigger_type,
            'trigger_data'              => $this->trigger_data,
            'allowance_signature'       => $this->allowance_signature,
            'settings'              => $this->settings,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'data_allowance_signature' => [
                'percentage_off'    => $percentage_off,
                'product_category'  => $productCategory ? [
                    'name'  => $productCategory->name,
                    'slug'  => $productCategory->slug,
                ] : null
            ],
        ];

        return array_merge($customOfferData, $basicOfferData ?? []);
    }
}
