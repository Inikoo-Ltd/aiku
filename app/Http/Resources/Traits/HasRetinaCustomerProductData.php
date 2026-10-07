<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Mar 2026 11:29:39 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Traits;

use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\CRM\WebUser;
use Illuminate\Support\Arr;

trait HasRetinaCustomerProductData
{
    /**
     * Get the common customer and product-related data for resources.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    protected function getCustomerProductData(\Illuminate\Http\Request $request): array
    {
        $customer = null;
        $user     = $request->user();
        if ($user instanceof WebUser) {
            $customer = $user->customer;
        }

        $favourite        = false;
        $back_in_stock_id = null;
        $back_in_stock    = false;

        if ($customer) {
            $favouriteProductIds = $request->attributes->get('retina_favourite_product_ids');
            if ($favouriteProductIds === null) {
                $favouriteProductIds = $customer->favourites()->whereNull('unfavourited_at')->pluck('product_id')->flip()->all();
                $request->attributes->set('retina_favourite_product_ids', $favouriteProductIds);
            }
            $favourite = isset($favouriteProductIds[$this->id]);

            $backInStockIds = $request->attributes->get('retina_back_in_stock_ids');
            if ($backInStockIds === null) {
                $backInStockIds = $customer->backInStockReminder()->pluck('id', 'product_id')->all();
                $request->attributes->set('retina_back_in_stock_ids', $backInStockIds);
            }
            if (isset($backInStockIds[$this->id])) {
                $back_in_stock    = true;
                $back_in_stock_id = $backInStockIds[$this->id];
            }
        }


        $productOffersData = json_decode($this->product_offers_data, true);

        $bestPercentageOff            = Arr::get($productOffersData, 'best_percentage_off.percentage_off', 0);
        $bestPercentageOffOfferFactor = 1 - (float)$bestPercentageOff;

        [$margin, $rrpPerUnit, $profit, $profitPerUnit, $units, $pricePerUnit] = $this->getPriceMetrics($this->rrp, $this->price, $this->units);
        [$marginDiscounted, , $profitDiscounted, $profitPerUnitDiscounted, , $pricePerUnitDiscounted] = $this->getPriceMetrics($this->rrp, $bestPercentageOffOfferFactor * $this->price, $this->units);

        return [
            'id'                         => $this->id,
            'code'                       => $this->code,
            'slug'                       => $this->slug,
            'name'                       => $this->name,
            'stock'                      => $this->available_quantity,
            'price'                      => $this->price,
            'rrp'                        => $this->rrp,
            'rrp_per_unit'               => $rrpPerUnit,
            'margin'                     => $margin,
            'profit'                     => $profit,
            'family_id'                  => $this->family_id,
            'state'                      => $this->state,
            'status'                     => $this->status,
            'created_at'                 => $this->created_at,
            'updated_at'                 => $this->updated_at,
            'units'                      => $units,
            'unit'                       => $this->unit,
            'url'                        => $this->canonical_url,
            'top_seller'                 => $this->top_seller,
            'web_images'                 => is_array($this->web_images) ? $this->web_images : json_decode($this->web_images),
            'transaction_id'             => $this->transaction_id ?? null,
            'quantity_ordered'           => (int)$this->quantity_ordered ?? 0,
            'quantity_ordered_new'       => (int)$this->quantity_ordered ?? 0,
            'is_favourite'               => $favourite,
            'is_back_in_stock'           => $back_in_stock,
            'back_in_stock_id'           => $back_in_stock_id,
            'profit_per_unit'            => $profitPerUnit,
            'price_per_unit'             => $pricePerUnit,
            'available_quantity'         => $this->available_quantity,
            'is_coming_soon'             => $this->status === ProductStatusEnum::COMING_SOON,
            'is_on_demand'               => $this->is_on_demand,
            'discounted_price'           => round($this->price * $bestPercentageOffOfferFactor, 2),
            'discounted_price_per_unit'  => $pricePerUnitDiscounted,
            'discounted_profit'          => $profitDiscounted,
            'discounted_profit_per_unit' => $profitPerUnitDiscounted,
            'discounted_margin'          => $marginDiscounted,
            'discounted_percentage'      => percentage($bestPercentageOff, 1),
            'product_offers_data'        => $productOffersData,
        ];
    }
}
