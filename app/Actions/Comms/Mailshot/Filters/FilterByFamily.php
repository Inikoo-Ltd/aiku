<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 26 Jan 2026 13:17:01 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\Filters;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;

class FilterByFamily
{
    /**
     * Apply the "By Family " filter to the query.
     *
     */
    public function apply(Builder $query, ?array $filters): Builder
    {

        $options = Arr::get($filters, 'by_family');

        if (is_array($options) && isset($options['value'])) {
            $val = $options['value'];
            $familyIds = $val['ids'] ?? [];
            $behaviours = $val['behaviors'] ?? [];
            $combinationLogic = $val['combine_logic'] ?? true;

            // Early return if required data is missing
            if (empty($familyIds) || empty($behaviours)) {
                return $query;
            }

            // Validate: AND logic should only have one behavior
            if (!$combinationLogic && count($behaviours) > 1) {
                \Log::warning('AND logic (combine_logic=false) requires exactly one behavior. Using first behavior only.');
                $behaviours = [reset($behaviours)];
            }

            // Normalize family IDs to array
            $familyIds = (array) $familyIds;


            $query->where(function ($q) use ($familyIds, $behaviours) {
                if (in_array('purchased', $behaviours)) {
                    $q->orWhereExists(function ($sub) use ($familyIds) {
                        $sub->selectRaw('1')
                            ->from('orders')
                            ->whereRaw('orders.customer_id = customers.id')
                            ->where('state', '!=', OrderStateEnum::CREATING)
                            ->whereExists(function ($tsub) use ($familyIds) {
                                $tsub->selectRaw('1')
                                    ->from('transactions')
                                    ->whereRaw('transactions.order_id = orders.id')
                                    ->whereIn('family_id', $familyIds);
                            });
                    });
                }

                if (in_array('favourited', $behaviours)) {
                    $q->orWhereExists(function ($fsub) use ($familyIds) {
                        $fsub->selectRaw('1')
                            ->from('favourites')
                            ->whereRaw('favourites.customer_id = customers.id')
                            ->whereIn('family_id', $familyIds);
                    });
                }

                if (in_array('basket_not_purchased', $behaviours)) {
                    $q->orWhereExists(function ($sub) use ($familyIds) {
                        $sub->selectRaw('1')
                            ->from('orders')
                            ->whereRaw('orders.customer_id = customers.id')
                            ->where('state', '=', OrderStateEnum::CREATING)
                            ->whereExists(function ($tsub) use ($familyIds) {
                                $tsub->selectRaw('1')
                                    ->from('transactions')
                                    ->whereRaw('transactions.order_id = orders.id')
                                    ->whereIn('family_id', $familyIds);
                            });
                    });
                }
            });
        }

        return $query;
    }
}
