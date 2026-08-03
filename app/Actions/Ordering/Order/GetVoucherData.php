<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Jun 2026 10:27:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Models\Catalogue\Product;
use App\Models\Discounts\Offer;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetVoucherData
{
    use AsObject;

    public function handle(?int $offerId): ?array
    {
        if (!$offerId) {
            return null;
        }

        $offer = Offer::find($offerId);
        if (!$offer) {
            return null;
        }

        /** @var \App\Models\Discounts\OfferAllowance $allowance */
        $allowance = $offer->offerAllowances()->first();
        $discount = Arr::get($allowance->data, 'percentage_off', 0);

        $discount = percentage($discount, 1);

        $giftProductCode = null;
        if ($offer->allowance_type === 'gift') {
            $giftProductId   = Arr::get($allowance->data, 'product_id');
            $giftProductCode = $giftProductId ? Product::find($giftProductId)?->code : null;
        }

        return [
            'id'                => $offer->id,
            'allowance_type'    => $offer->allowance_type,
            'voucher_code'      => $offer->code,
            'voucher_amount'    => Arr::get($offer->trigger_data, 'item_amount'),
            'state'             => $offer->state->value,
            'status'            => $offer->status,
            'start_at'          => $offer->start_at,
            'end_at'            => $offer->end_at,
            'name'              => $offer->name,
            'discount'          => $discount,
            'gift_product_code' => $giftProductCode
        ];
    }
}
