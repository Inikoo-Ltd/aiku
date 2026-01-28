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
        if ($subDeptFilter && !empty($subDeptFilter['value'])) {
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

            if (!empty($valueToSend['ids'])) {

                $subdepartmentIds = [];
                $behaviors = ['purchased'];

                if (is_array($valueToSend)) {

                    if (isset($valueToSend['ids'])) {
                        $subdepartmentIds = $valueToSend['ids'];

                        if (isset($valueToSend['behaviors']) && is_array($valueToSend['behaviors'])) {
                            $behaviors = $valueToSend['behaviors'];
                        }
                    } else {
                        $subdepartmentIds = array_filter($valueToSend, function ($item) {
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
            }
        }

        return $query;
    }
}
