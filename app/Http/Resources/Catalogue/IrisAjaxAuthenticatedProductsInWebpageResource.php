<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 15:08:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\HasPriceMetrics;

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
 * @property mixed $group_id
 * @property mixed $organisation_id
 * @property mixed $webpage_id
 * @property mixed $website_id
 * @property mixed $shop_id
 * @property mixed $quantity_ordered
 * @property mixed $canonical_url
 * @property mixed $offers_data
 */
class IrisAjaxAuthenticatedProductsInWebpageResource extends JsonResource
{
    use HasSelfCall;
    use HasPriceMetrics;


    public function toArray($request): array
    {



        $favourite        = false;
        $back_in_stock_id = null;
        $back_in_stock    = false;

        if ($request->user()) {

            $customer = $request->user()->customer;
            if ($customer) {
                $favourite = $customer->favourites()?->where('product_id', $this->id)->first();
            }
        }


        if ($request->user()) {
            $customer = $request->user()->customer;
            if ($customer) {
                $set_data_back_in_stock = $customer->BackInStockReminder()
                    ?->where('product_id', $this->id)
                    ->first();

                if ($set_data_back_in_stock) {
                    $back_in_stock    = true;
                    $back_in_stock_id = $set_data_back_in_stock->id;
                }
            }
        }




        [$margin, $rrpPerUnit, $profit, $profitPerUnit, $units, $pricePerUnit] = $this->getPriceMetrics($this->rrp, $this->price, $this->units);


        return [
            'id'                            => $this->id,
            'stock'                         => $this->available_quantity,
            'is_favourite'                  => $favourite && !$favourite->unfavourited_at ?? false,
            'is_back_in_stock'              => $back_in_stock,
            'back_in_stock_id'              => $back_in_stock_id,
            'available_quantity'            => $this->available_quantity,
            'is_coming_soon'                => $this->status === ProductStatusEnum::COMING_SOON,




        ];
    }


}
