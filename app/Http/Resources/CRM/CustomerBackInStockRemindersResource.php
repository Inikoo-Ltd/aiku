<?php

/*
 * author Arya Permana - Kirin
 * created on 16-10-2024-10h-59m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Http\Resources\Traits\HasPriceMetrics;
use App\Http\Resources\Helpers\ImageResource;

/**
 * @property mixed $id
 * @property mixed $code
 * @property mixed $name
 * @property mixed $available_quantity
 * @property mixed $price
 * @property mixed $rrp
 * @property mixed $state
 * @property mixed $status
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $units
 * @property mixed $unit
 * @property mixed $canonical_url
 * @property mixed $web_images
 * @property mixed $slug
 */
class CustomerBackInStockRemindersResource extends JsonResource
{
    use HasSelfCall;
    use HasPriceMetrics;

    public function toArray($request): array
    {
        $favourite = false;
        if ($request->user()) {
            $customer = $request->user()->customer;
            if ($customer) {
                $favourite = $customer->favourites()?->where('product_id', $this->id)->first();
            }
        }

        $back_in_stock_id = null;
        $back_in_stock    = false;

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
            'id'                   => $this->id,
            'code'                 => $this->code,
            'name'                 => $this->name,
            'stock'                => $this->available_quantity,
            'price'                => $this->price,
            'rrp'                  => $this->rrp,
            'rrp_per_unit'         => $rrpPerUnit,
            'margin'               => $margin,
            'profit'               => $profit,
            'state'                => $this->state,
            'status'               => $this->status,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'units'                => $units,
            'unit'                 => $this->unit,
            'url'                  => $this->canonical_url,
            'top_seller'           => $this->top_seller,
            'web_images'           => json_decode($this->web_images),
            'transaction_id'       => $this->transaction_id ?? null,
            'quantity_ordered'     => (int)$this->quantity_ordered ?? 0,
            'quantity_ordered_new' => (int)$this->quantity_ordered ?? 0,  // To editable in Frontend
            'is_favourite'         => $favourite && !$favourite->unfavourited_at ?? false,
            'is_back_in_stock'     => $back_in_stock,
            'back_in_stock_id'     => $back_in_stock_id,
            'profit_per_unit'      => $profitPerUnit,
            'price_per_unit'       => $pricePerUnit,
            'available_quantity'   => $this->available_quantity,
            'is_coming_soon'       => $this->status === ProductStatusEnum::COMING_SOON,
            'is_on_demand'         => $this->is_on_demand
        ];
    }
}
