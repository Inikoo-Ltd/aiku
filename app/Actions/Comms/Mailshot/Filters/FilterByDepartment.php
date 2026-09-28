<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 30 Jan 2026 09:30:01 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\Filters;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FilterByDepartment
{
    /**
     * Apply the "By Department" filter.
     *
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $deptFilter = Arr::get($filters, 'by_departments');

        if (is_array($deptFilter) && isset($deptFilter['value'])) {
            $val = $deptFilter['value'];
            $departmentIds = $val['ids'] ?? [];
            $behaviors = $val['behaviors'] ?? [];
            $combinationLogic = $val['combine_logic'] ?? true;

            // Early return if required data is missing
            if (empty($departmentIds) || empty($behaviors)) {
                return $query;
            }

            // Validate: AND logic should only have one behavior
            if (!$combinationLogic && count($behaviors) > 1) {
                \Log::warning('AND logic (combine_logic=false) requires exactly one behavior. Using first behavior only.');
                $behaviors = [reset($behaviors)];
            }

            // Normalize department IDs to array
            $departmentIds = (array) $departmentIds;

            $includesPurchased = in_array('purchased', $behaviors);
            $includesInBasket  = in_array('basket_not_purchased', $behaviors);

            if (!$includesPurchased && !$includesInBasket) {
                return $query;
            }

            $query->whereExists(function ($subQuery) use ($departmentIds, $includesPurchased, $includesInBasket) {
                $subQuery->select(DB::raw(1))
                    ->from('orders')
                    ->join('transactions', 'orders.id', '=', 'transactions.order_id')
                    ->whereRaw('orders.customer_id = customers.id')
                    ->whereIn('transactions.department_id', $departmentIds)
                    ->whereNull('orders.deleted_at');

                if (!$includesInBasket) {
                    $subQuery->where('orders.state', '!=', OrderStateEnum::CREATING);
                } elseif (!$includesPurchased) {
                    $subQuery->where('orders.state', OrderStateEnum::CREATING);
                }
            });
        }

        return $query;
    }
}
