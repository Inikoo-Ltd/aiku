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

        // make sure $filters is an array
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

            // Define behavior query builders
            $behaviorQueries = [
                'purchased' => function ($q) use ($familyIds) {
                    return $q->whereHas('orders', function ($orderQuery) use ($familyIds) {
                        $orderQuery->where('state', '!=', OrderStateEnum::CREATING->value)
                            ->whereHas('transactions', function ($transactionQuery) use ($familyIds) {
                                $transactionQuery->whereIn('family_id', $familyIds);
                            });
                    });
                },
                'favourited' => function ($q) use ($familyIds) {
                    return $q->whereHas('favourites', function ($favouriteQuery) use ($familyIds) {
                        $favouriteQuery->whereIn('family_id', $familyIds);
                    });
                },
                'basket_not_purchased' => function ($q) use ($familyIds) {
                    return $q->whereHas('orders', function ($orderQuery) use ($familyIds) {
                        $orderQuery->where('state', OrderStateEnum::CREATING->value)
                            ->whereHas('transactions', function ($transactionQuery) use ($familyIds) {
                                $transactionQuery->whereIn('family_id', $familyIds);
                            });
                    });
                },
            ];

            if ($combinationLogic) {
                // OR logic: Wrap all conditions in a single where closure
                $query->where(function ($q) use ($behaviours, $behaviorQueries) {
                    foreach ($behaviours as $index => $behaviour) {
                        if (!isset($behaviorQueries[$behaviour])) {
                            continue;
                        }

                        if ($index === 0) {
                            $behaviorQueries[$behaviour]($q);
                        } else {
                            // Use orWhere with a closure to maintain proper grouping
                            $q->orWhere(function ($subQuery) use ($behaviour, $behaviorQueries) {
                                $behaviorQueries[$behaviour]($subQuery);
                            });
                        }
                    }
                });
            } else {
                // AND logic: Apply single behavior (already validated above)
                $behaviour = reset($behaviours);
                if (isset($behaviorQueries[$behaviour])) {
                    $behaviorQueries[$behaviour]($query);
                }
            }
        }

        return $query;
    }
}
