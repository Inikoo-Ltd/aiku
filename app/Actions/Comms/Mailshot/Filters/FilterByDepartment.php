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
                    $q->orWhereHas('orders', function ($oq) use ($departmentIds) {
                        $oq->where('state', '!=', OrderStateEnum::CREATING)
                            ->whereHas('transactions', function ($tq) use ($departmentIds) {
                                $tq->whereIn('department_id', $departmentIds);
                            });
                    });
                }
                if (in_array('favourited', $behaviors)) {
                    $q->orWhereHas('favourites', function ($favouriteQuery) use ($departmentIds) {
                        $favouriteQuery->whereIn('department_id', $departmentIds);
                    });
                }

                if (in_array('basket_not_purchased', $behaviors)) {
                    $q->orWhereHas('orders', function ($oq) use ($departmentIds) {
                        $oq->where('state', OrderStateEnum::CREATING)
                            ->whereHas('transactions', function ($tq) use ($departmentIds) {
                                $tq->whereIn('department_id', $departmentIds);
                            });
                    });
                }
            });
        }

        return $query;
    }
}
