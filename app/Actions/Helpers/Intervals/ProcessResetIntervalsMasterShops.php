<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateInvoiceIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateRegistrationIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateSalesIntervals;
use App\Models\Masters\MasterShop;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsMasterShops
{
    use AsAction;

    public function handle(array $intervals, array $doPreviousPeriods): void
    {
        foreach (MasterShop::all() as $masterShop) {
            MasterShopHydrateSalesIntervals::dispatch(
                masterShopID: $masterShop->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            MasterShopHydrateInvoiceIntervals::dispatch(
                masterShopID: $masterShop->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            MasterShopHydrateRegistrationIntervals::dispatch(
                masterShopID: $masterShop->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            MasterShopHydrateOrderInBasketAtCreatedIntervals::dispatch(
                masterShopID: $masterShop->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            MasterShopHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch(
                masterShopID: $masterShop->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );
        }
    }
}
