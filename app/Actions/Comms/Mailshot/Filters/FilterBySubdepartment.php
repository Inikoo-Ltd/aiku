<?php

namespace App\Actions\Comms\Mailshot\Filters;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

class FilterBySubdepartment
{
    /**
     * Apply the "By Subdepartment" filter.
     *
     * @param SpatieQueryBuilder|Builder $query
     * @param array $filters
     * @return Builder
     */
    public function apply($query, array $filters)
    {
        $subDeptFilter = Arr::get($filters, 'by_subdepartment');

        if (is_array($subDeptFilter) && isset($subDeptFilter['value'])) {
            $val = $subDeptFilter['value'];
            $subdepartmentIds = $val['ids'] ?? [];
            $behaviors = $val['behaviors'] ?? [];
            $combinationLogic = $val['combine_logic'] ?? true;

            // Early return if required data is missing
            if (empty($subdepartmentIds) || empty($behaviors)) {
                return $query;
            }

            // Validate: AND logic should only have one behavior
            if (!$combinationLogic && count($behaviors) > 1) {
                \Log::warning('AND logic (combine_logic=false) requires exactly one behavior. Using first behavior only.');
                $behaviors = [reset($behaviors)];
            }

            // Normalize subdepartment IDs to array
            $subdepartmentIds = (array) $subdepartmentIds;

            $query->where(function ($q) use ($subdepartmentIds, $behaviors) {
                if (in_array('purchased', $behaviors)) {
                    $q->orWhereExists(function ($subQuery) use ($subdepartmentIds) {
                        $subQuery->select(DB::raw(1))
                            ->from('orders')
                            ->join('order_transactions', 'orders.id', '=', 'order_transactions.order_id')
                            ->whereRaw('orders.customer_id = customers.id')
                            ->where('orders.state', '!=', OrderStateEnum::CREATING)
                            ->whereIn('order_transactions.sub_department_id', $subdepartmentIds)
                            ->whereNull('orders.deleted_at');
                    });
                }

                if (in_array('in_basket', $behaviors)) {
                    $q->orWhereExists(function ($subQuery) use ($subdepartmentIds) {
                        $subQuery->select(DB::raw(1))
                            ->from('orders')
                            ->join('order_transactions', 'orders.id', '=', 'order_transactions.order_id')
                            ->whereRaw('orders.customer_id = customers.id')
                            ->where('orders.state', OrderStateEnum::CREATING)
                            ->whereIn('order_transactions.sub_department_id', $subdepartmentIds)
                            ->whereNull('orders.deleted_at');
                    });
                }
            });
        }

        return $query;
    }
}
