<?php

/*
 * author Louis Perez
 * created on 10-06-2026-16h-07m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Ordering\Order;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class CleanFinishedVouchers implements ShouldBeUnique
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function getJobUniqueId(int $offerId): string
    {
        return $offerId;
    }

    public function handle(int $offerId): void
    {
        DB::table('orders')
            ->where('voucher_offer_id', $offerId)
            ->where('state', OrderStateEnum::CREATING)
            ->update(['voucher_offer_id' => null]);


    }

}
