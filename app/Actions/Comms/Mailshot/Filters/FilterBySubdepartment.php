<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Database\Eloquent\Builder;
use App\Enums\Ordering\Order\OrderStateEnum;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

class FilterBySubdepartment
{
    /**
     * Apply the "By Subdepartment" filter.
     *
     * @param SpatieQueryBuilder|Builder $query
     * @param mixed $value Could be array ['ids' => [], 'behaviors' => []] OR just ids array
     * @return Builder
     */
    public function apply($query, $value)
    {

        $subdepartmentIds = [];
        $behaviors = ['purchased'];

        if (is_array($value)) {

            if (isset($value['ids'])) {
                $subdepartmentIds = $value['ids'];

                if (isset($value['behaviors']) && is_array($value['behaviors'])) {
                    $behaviors = $value['behaviors'];
                }
            } else {
                $subdepartmentIds = array_filter($value, function ($item) {
                    return is_numeric($item);
                });
            }
        }


        if (!is_array($subdepartmentIds)) {
            $subdepartmentIds = [$subdepartmentIds];
        }


        if (empty($subdepartmentIds)) {
            return $query;
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
