<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\Ordering\Order\OrderStateEnum;
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

        if (!$subDeptFilter || empty($subDeptFilter['value'])) {
            return $query;
        }

        $rawValue = $subDeptFilter['value'];
        $valueToSend = [
            'ids' => [],
            'behaviors' => ['purchased']
        ];

        if (is_array($rawValue)) {
            if (array_key_exists('ids', $rawValue)) {
                $valueToSend['ids'] = $rawValue['ids'] ?? [];

                if (isset($rawValue['behaviors']) && is_array($rawValue['behaviors'])) {
                    $valueToSend['behaviors'] = $rawValue['behaviors'];
                }
            } elseif (array_key_exists(0, $rawValue)) {
                $valueToSend['ids'] = $rawValue;
            } elseif (isset($rawValue['behaviors'])) {
                $valueToSend['behaviors'] = $rawValue['behaviors'];
            }
        } else {
            $valueToSend['ids'] = [$rawValue];
        }

        if (empty($valueToSend['ids'])) {
            return $query;
        }

        $subdepartmentIds = $valueToSend['ids'];
        $behaviors = $valueToSend['behaviors'];

        if (!is_array($subdepartmentIds)) {
            $subdepartmentIds = [$subdepartmentIds];
        }

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

        return $query;
    }
}
