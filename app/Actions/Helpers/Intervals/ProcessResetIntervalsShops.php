<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoiceIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrdersDispatchedToday;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateFinalised;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateRegistrationIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSalesIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateVisitorsIntervals;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervals;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsShops
{
    use AsAction;

    public string $commandSignature = 'aiku:process-reset-intervals-shops';

    public function handle(array $intervals = [], array $doPreviousPeriods = []): void
    {
        /** @var Shop $shop */
        foreach (
            Shop::whereIn('state', [
                ShopStateEnum::OPEN,
                ShopStateEnum::CLOSING_DOWN
            ])->get() as $shop
        ) {
            if (array_intersect($this->getIntervalValues($intervals), [
                DateIntervalEnum::YESTERDAY->value,
                DateIntervalEnum::TODAY->value
            ])) {
                ShopHydrateOrderStateFinalised::dispatch($shop->id);
                ShopHydrateOrdersDispatchedToday::dispatch($shop->id);
            }

            ShopHydrateSalesIntervals::dispatch(
                shop: $shop,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            ShopHydrateInvoiceIntervals::dispatch(
                shop: $shop,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            ShopHydrateRegistrationIntervals::dispatch(
                shopId: $shop->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            ShopHydrateOrderIntervals::dispatch(
                shop: $shop,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            ShopHydrateOrderInBasketAtCreatedIntervals::dispatch(
                shop: $shop,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            ShopHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch(
                shop: $shop,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            ShopHydrateVisitorsIntervals::dispatch(
                shop: $shop,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            if ($shop->type == ShopTypeEnum::DROPSHIPPING) {
                ShopHydratePlatformSalesIntervals::dispatch(
                    shop: $shop,
                    intervals: $intervals,
                    doPreviousPeriods: $doPreviousPeriods
                );
            }
        }

        foreach (
            Shop::whereNotIn('state', [
                ShopStateEnum::OPEN,
                ShopStateEnum::CLOSING_DOWN
            ])->get() as $shop
        ) {
            ShopHydrateSalesIntervals::dispatch(
                shop: $shop,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            ShopHydrateInvoiceIntervals::dispatch(
                shop: $shop,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            ShopHydrateRegistrationIntervals::dispatch(
                shopId: $shop->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            ShopHydrateOrderIntervals::dispatch(
                shop: $shop,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            ShopHydrateOrderInBasketAtCreatedIntervals::dispatch(
                shop: $shop,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            ShopHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch(
                shop: $shop,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            ShopHydrateVisitorsIntervals::dispatch(
                shop: $shop,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            if ($shop->type == ShopTypeEnum::DROPSHIPPING) {
                ShopHydratePlatformSalesIntervals::dispatch(
                    shop: $shop,
                    intervals: $intervals,
                    doPreviousPeriods: $doPreviousPeriods
                )->delay(now()->addMinute())->onQueue('low-priority');
            }
        }
    }

    private function getIntervalValues(array $intervals): array
    {
        return array_map(static function ($interval) {
            if ($interval instanceof DateIntervalEnum) {
                return $interval->value;
            }

            return $interval;
        }, $intervals);
    }
}
