<?php

namespace App\Actions\Comms\Mailshot\Filters;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
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
                    $q->orWhereHas('orders', function ($oq) use ($subdepartmentIds) {
                        $oq->where('state', '!=', OrderStateEnum::CREATING)
                            ->whereHas('transactions', function ($tq) use ($subdepartmentIds) {
                                $tq->whereIn('sub_department_id', $subdepartmentIds);
                            });
                    });
                }

                if (in_array('in_basket', $behaviors)) {
                    $q->orWhereHas('orders', function ($oq) use ($subdepartmentIds) {
                        $oq->where('state', OrderStateEnum::CREATING)
                            ->whereHas('transactions', function ($tq) use ($subdepartmentIds) {
                                $tq->whereIn('sub_department_id', $subdepartmentIds);
                            });
                    });
                }
            });
        }

        return $query;
    }
}
