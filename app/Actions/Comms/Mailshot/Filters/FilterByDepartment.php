<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 30 Jan 2026 09:30:01 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\Filters;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

class FilterByDepartment
{
    /**
     * Apply the "By Department" filter.
     *
     * @param SpatieQueryBuilder|Builder $query
     * @param array $filters
     * @return Builder
     */
    public function apply($query, array $filters)
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

            $query->where(function ($q) use ($departmentIds, $behaviors) {
                if (in_array('purchased', $behaviors)) {
                    $q->orWhereExists(function ($subQuery) use ($departmentIds) {
                        $subQuery->select(DB::raw(1))
                            ->from('orders')
                            ->join('order_transactions', 'orders.id', '=', 'order_transactions.order_id')
                            ->whereRaw('orders.customer_id = customers.id')
                            ->where('orders.state', '!=', OrderStateEnum::CREATING)
                            ->whereIn('order_transactions.department_id', $departmentIds)
                            ->whereNull('orders.deleted_at');
                    });
                }

                if (in_array('basket_not_purchased', $behaviors)) {
                    $q->orWhereExists(function ($subQuery) use ($departmentIds) {
                        $subQuery->select(DB::raw(1))
                            ->from('orders')
                            ->join('order_transactions', 'orders.id', '=', 'order_transactions.order_id')
                            ->whereRaw('orders.customer_id = customers.id')
                            ->where('orders.state', OrderStateEnum::CREATING)
                            ->whereIn('order_transactions.department_id', $departmentIds)
                            ->whereNull('orders.deleted_at');
                    });
                }
            });
        }

        return $query;
    }
}
