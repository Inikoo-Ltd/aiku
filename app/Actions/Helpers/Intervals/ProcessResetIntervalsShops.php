<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrdersDispatchedToday;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateFinalised;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsShops
{
    use AsAction;

    public string $commandSignature = 'aiku:process-reset-intervals-shops';

    public function handle(array $intervals = []): void
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
