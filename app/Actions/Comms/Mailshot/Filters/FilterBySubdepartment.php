<?php

namespace App\Actions\Comms\Mailshot\Filters;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Support\Arr;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FilterBySubdepartment
{
    /**
     * Apply the "By Subdepartment" filter.
     *
     */
    public function apply(Builder $query, array $filters): Builder
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

            $includesPurchased = in_array('purchased', $behaviors);
            $includesInBasket  = in_array('in_basket', $behaviors);

            if (!$includesPurchased && !$includesInBasket) {
                return $query;
            }

            $query->whereExists(function ($subQuery) use ($subdepartmentIds, $includesPurchased, $includesInBasket) {
                $subQuery->select(DB::raw(1))
                    ->from('orders')
                    ->join('transactions', 'orders.id', '=', 'transactions.order_id')
                    ->whereRaw('orders.customer_id = customers.id')
                    ->whereIn('transactions.sub_department_id', $subdepartmentIds)
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
