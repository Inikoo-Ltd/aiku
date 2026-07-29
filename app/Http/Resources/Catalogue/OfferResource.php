<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Apr 2023 15:23:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Actions\Discounts\Offer\UpdateProductCategoryOffersData;
use App\Enums\Discounts\Offer\OfferTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property int $shop_id
 * @property int $offer_campaign_id
 * @property string $slug
 * @property string $code
 * @property string $data
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property mixed $duration
 * @property mixed $start_at
 * @property mixed $end_at
 * @property mixed $trigger_type
 * @property mixed $trigger_data
 * @property mixed $allowance_signature
 * @property mixed $settings
 * @property mixed $type
 *
 */
class OfferResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Offer $offer */
        $offer = $this->resource;

        preg_match('/percentage_off:([0-9]*\.?[0-9]+)/', $this->allowance_signature, $matches);
        $percentage_off = $matches[1] ?? null;

        preg_match('/^all_products_in_product_category(?::(\d+))?:/', $this->allowance_signature, $m);
        $productCategory = isset($m[1]) ? ProductCategory::find($m[1]) : null;


        $basicOfferData = UpdateProductCategoryOffersData::make()->getBasicOfferData($offer);

        $customOfferData = [
            'shop_id'                  => $this->shop_id,
            'offer_campaign_id'        => $this->offer_campaign_id,
            'offer_campaign'           => $offer->offerCampaign ? [
                'id'   => $offer->offerCampaign->id,
                'slug' => $offer->offerCampaign->slug,
                'name' => $offer->offerCampaign->name,
            ] : null,
            'slug'                     => $this->slug,
            'type'                     => $this->type,
            'code'                     => $this->code,
            'name'                     => $this->name,
            'duration'                 => $this->duration?->value,
            'start_at'                 => $this->start_at,
            'end_at'                   => $this->end_at,
            'data'                     => $this->data,
            'trigger_type'             => $this->trigger_type,
            'trigger_data'             => $this->trigger_data,
            'allowance_signature'      => $this->allowance_signature,
            'settings'                 => $this->settings,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
            'data_allowance_signature' => [
                'percentage_off'   => $percentage_off,
                'product_category' => $productCategory ? [
                    'name' => $productCategory->name,
                    'slug' => $productCategory->slug,
                    'type' => $productCategory->type->value,
                ] : null
            ],
        ];

        if ($offer->type == OfferTypeEnum::GIFT->value) {
            $customOfferData['gift_data'] = $this->getGiftData($offer);
        }

        return array_merge($customOfferData, $basicOfferData ?? []);
    }

    /**
     * @return array{trigger_type: string|null, min_order_amount: float|null, item_quantity: int|null, quantity: int, product: array|null, trigger_product: array|null}
     */
    protected function getGiftData(Offer $offer): array
    {
        /** @var OfferAllowance $giftAllowance */
        $giftAllowance = $offer->offerAllowances()->where('type', OfferAllowanceType::GIFT)->first();

        $giftProduct    = $giftAllowance ? Product::find(Arr::get($giftAllowance->data, 'product_id')) : null;
        $trigger        = $offer->trigger;
        $triggerProduct = $trigger instanceof Product ? $trigger : null;

        return [
            'trigger_type'     => $offer->trigger_type,
            'min_order_amount' => Arr::get($offer->trigger_data, 'min_order_amount'),
            'item_quantity'    => Arr::get($offer->trigger_data, 'item_quantity'),
            'quantity'         => (int)Arr::get($giftAllowance?->data, 'quantity', 1),
            'product'          => $this->getGiftProductData($giftProduct),
            'trigger_product'  => $this->getGiftProductData($triggerProduct),
        ];
    }

    /**
     * @return array{id: int, slug: string, code: string, name: string|null, price: float|null, image: array|null}|null
     */
    protected function getGiftProductData(?Product $product): ?array
    {
        if (!$product) {
            return null;
        }

        return [
            'id'    => $product->id,
            'slug'  => $product->slug,
            'code'  => $product->code,
            'name'  => $product->name,
            'price' => $product->price !== null ? (float)$product->price : null,
            'image' => Arr::get($product->web_images, 'main.gallery'),
        ];
    }
}
