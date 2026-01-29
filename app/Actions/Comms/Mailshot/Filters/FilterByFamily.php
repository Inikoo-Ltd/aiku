<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 26 Jan 2026 13:17:01 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\Filters;

use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;
use Illuminate\Support\Arr;

class FilterByFamily
{
    /**
     * Apply the "By Family " filter to the query.
     *
     * @param Builder|SpatieQueryBuilder $query
     * @param int|string $familyId
     * @return Builder|SpatieQueryBuilder
     */
    public function apply($query, ?array $filters)
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
                    $q->orWhereHas('orders', function ($oq) use ($familyIds) {
                        $oq->where('state', '!=', OrderStateEnum::CREATING)
                            ->whereHas('transactions', function ($tq) use ($familyIds) {
                                $tq->whereIn('family_id', $familyIds);
                            });
                    });
                }

                if (in_array('favourited', $behaviours)) {
                    $q->orWhereHas('favourites', function ($favouriteQuery) use ($familyIds) {
                        $favouriteQuery->whereIn('family_id', $familyIds);
                    });
                }

                if (in_array('basket_not_purchased', $behaviours)) {
                    $q->orWhereHas('orders', function ($oq) use ($familyIds) {
                        $oq->where('state', OrderStateEnum::CREATING)
                            ->whereHas('transactions', function ($tq) use ($familyIds) {
                                $tq->whereIn('family_id', $familyIds);
                            });
                    });
                }
            });
        }

        return $query;
    }
}
