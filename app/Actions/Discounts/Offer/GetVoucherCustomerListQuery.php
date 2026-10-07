<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 02 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\Offer;

use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerTradeStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetVoucherCustomerListQuery
{
    use AsObject;

    public const array STATES = [
        CustomerStateEnum::LOSING->value,
        CustomerStateEnum::LOST->value,
    ];

    /**
     * @param array{states?: array<int, string>, ordered_once?: bool, ordered_once_from_months?: int, ordered_once_to_months?: int} $segments
     */
    public function handle(Shop $shop, array $segments): Builder
    {
        $states      = array_values(array_intersect(Arr::get($segments, 'states', []), self::STATES));
        $orderedOnce = (bool) Arr::get($segments, 'ordered_once', false);

        $query = DB::table('customers')
            ->where('customers.shop_id', $shop->id)
            ->whereNull('customers.deleted_at');

        if (!$states && !$orderedOnce) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($states, $orderedOnce, $segments) {
            if ($states) {
                $query->orWhereIn('customers.state', $states);
            }

            if ($orderedOnce) {
                $fromMonths = (int) Arr::get($segments, 'ordered_once_from_months', 0);
                $toMonths   = (int) Arr::get($segments, 'ordered_once_to_months', $fromMonths);

                $query->orWhere(function (Builder $query) use ($fromMonths, $toMonths) {
                    $query->where('customers.trade_state', CustomerTradeStateEnum::ONE->value)
                        ->whereBetween('customers.last_invoiced_at', [
                            now()->subMonths(max($fromMonths, $toMonths))->startOfDay(),
                            now()->subMonths(min($fromMonths, $toMonths))->endOfDay(),
                        ]);
                });
            }
        });
    }
}
