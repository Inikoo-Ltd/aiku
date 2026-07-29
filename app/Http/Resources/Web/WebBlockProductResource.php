<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:12:46 Central Indonesia Time, Sanur, change, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Traits\HasBucketImages;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Catalogue\TagResource;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Traits\HasPriceMetrics;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $units
 * @property mixed $rrp
 * @property mixed $id
 */
class WebBlockProductResource extends JsonResource
{
    use HasSelfCall;
    use HasBucketImages;
    use HasPriceMetrics;


    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        $countriesOrigin = [];
        $countries      = array_filter(array_map('trim', explode(',', $product->country_of_origin ?? '')));
        foreach ($countries as $country) {
            $countriesOrigin[] = NaturalLanguage::make()->country($country);
        }

        $specifications = [
            'countries_of_origin' => $countriesOrigin,
            'ingredients'       => $product->marketing_ingredients,
            'gross_weight'      => $product->gross_weight,
            'barcode'           => $product->barcode,
            'dimensions'        => NaturalLanguage::make()->dimensions(json_encode($product->marketing_dimensions)),
            'cpnp'              => $product->cpnp_number,
            'marketing_weight'  => $product->marketing_weight,
            'unit'              => $product->unit,
        ];

        [$margin, $rrpPerUnit, $profit, $profitPerUnit, $units, $pricePerUnit] = $this->getPriceMetrics($product->rrp, $product->price, $product->units);

        if (is_array($product->offers_data)) {
            $productOffersData = $product->offers_data;
        } else {
            $productOffersData = json_decode($product->offers_data, true);
        }


        $bestPercentageOff            = Arr::get($productOffersData, 'best_percentage_off.percentage_off', 0);
        $bestPercentageOffOfferFactor = 1 - (float)$bestPercentageOff;

        [$marginDiscounted, , $profitDiscounted, $profitPerUnitDiscounted, , $pricePerUnitDiscounted] = $this->getPriceMetrics($product->rrp, $bestPercentageOffOfferFactor * $product->price, $product->units);


        $back_in_stock = false;

        if ($request->user()) {
            /** @var Customer $customer */
            $customer = $request->user()->customer;
            if ($customer) {
                $set_data_back_in_stock = $customer->backInStockReminder()
                    ?->where('product_id', $this->id)
                    ->first();

                if ($set_data_back_in_stock) {
                    $back_in_stock = true;
                }
            }
        }


        return [
            'luigi_identity'    => $product->getLuigiIdentity(),
            'slug'              => $product->slug,
            'code'              => $product->code,
            'family_code'       => $product->family?->code,
            'name'              => $product->name,
            'description'       => $product->description,
            'description_title' => $product->description_title,
            'description_extra' => $product->description_extra,
            'stock'             => $product->available_quantity,
            'specifications'    => $specifications,
            'contents'          => ModelHasContentsResource::collection($product->contents)->toArray($request),
            'id'                => $product->id,
            'image_id'          => $product->image_id,
            'currency_code'     => $product->currency->code,
            'rrp'               => $product->rrp,
            'rrp_per_unit'      => $rrpPerUnit,
            'margin'            => $margin,
            'profit'            => $profit,
            'profit_per_unit'   => $profitPerUnit,
            'price'             => $product->price,
            'price_per_unit'    => $pricePerUnit,
            'status'            => $product->status,
            'status_label'      => $product->status->labels()[$product->status->value],
            'state'             => $product->state,
            'units'             => $units,
            'unit'              => $product->unit,
            'web_images'        => $product->web_images,
            'created_at'        => $product->created_at,
            'updated_at'        => $product->updated_at,
            'images'            => $product->bucket_images ? $this->getImagesData($product, true, 800) : $this->getResizedMediaImages($product, 800),
            'tags'              => TagResource::collection($product->tags)->toArray($request),
            'is_coming_soon'    => $product->status === ProductStatusEnum::COMING_SOON,
            'is_on_demand'      => $product->is_on_demand,
            'is_back_in_stock'  => $product->backInStockReminders,
            'back_in_stock'     => $back_in_stock,


            'discounted_price'           => round($product->price * $bestPercentageOffOfferFactor, 2),
            'discounted_price_per_unit'  => $pricePerUnitDiscounted,
            'discounted_profit'          => $profitDiscounted,
            'discounted_profit_per_unit' => $profitPerUnitDiscounted,
            'discounted_margin'          => $marginDiscounted,
            'discounted_percentage'      => percentage($bestPercentageOff, 1),

            'is_single_trade_unit'       => $product->is_single_trade_unit,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getResizedMediaImages($product, int $maxWidth): array
    {
        return $product->images->map(fn ($media) => [
            'id'        => $media->id,
            'source'    => GetPictureSources::run($media->getImage()->resize($maxWidth, $maxWidth)),
            'thumbnail' => GetPictureSources::run($media->getImage()->resize(0, 48)),
            'alt'       => $media->pivot?->caption,
        ])->all();
    }
}
