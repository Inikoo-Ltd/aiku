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
        $options = Arr::get($filters, 'by_family', []);
        if (empty($options)) {
            return $query;
        }




        return $query;

        $familyIds = Arr::get($options, 'family_ids');
        $behaviours = Arr::get($options, 'behaviours');
        $combinationLogic = Arr::get($options, 'combination_logic');

        if (empty($familyIds)) {
            return $query;
        }

        $familyIds = is_array($familyIds) ? $familyIds : [$familyIds];

        // Collect all active behaviours
        $activeBehaviours = [];
        if ($behaviours['purchased'] ?? false) {
            $activeBehaviours[] = 'purchased';
        }
        if ($behaviours['favourited'] ?? false) {
            $activeBehaviours[] = 'favourited';
        }
        if ($behaviours['basket_not_purchased'] ?? false) {
            $activeBehaviours[] = 'basket_not_purchased';
        }

        if (empty($activeBehaviours)) {
            return $query;
        }

        // Apply filters based on combination logic
        if ($combinationLogic) {
            // OR conditions - use whereHas for first condition, orWhereHas for subsequent
            $firstBehaviour = array_shift($activeBehaviours);

            switch ($firstBehaviour) {
                case 'purchased':
                    $query->whereHas('orders', function ($q) use ($familyIds) {
                        $q->where('state', '!=', OrderStateEnum::CREATING->value)
                            ->whereHas('transactions', function ($tq) use ($familyIds) {
                                $tq->whereIn('family_id', $familyIds);
                            });
                    });
                    break;
                case 'favourited':
                    $query->whereHas('favourites', function ($q) use ($familyIds) {
                        $q->whereIn('family_id', $familyIds);
                    });
                    break;
                case 'basket_not_purchased':
                    $query->whereHas('orders', function ($q) use ($familyIds) {
                        $q->where('state', OrderStateEnum::CREATING->value)
                            ->whereHas('transactions', function ($tq) use ($familyIds) {
                                $tq->whereIn('family_id', $familyIds);
                            });
                    });
                    break;
            }

            // Apply remaining behaviours with OR conditions
            foreach ($activeBehaviours as $behaviour) {
                switch ($behaviour) {
                    case 'purchased':
                        $query->orWhereHas('orders', function ($q) use ($familyIds) {
                            $q->where('state', '!=', OrderStateEnum::CREATING->value)
                                ->whereHas('transactions', function ($tq) use ($familyIds) {
                                    $tq->whereIn('family_id', $familyIds);
                                });
                        });
                        break;
                    case 'favourited':
                        $query->orWhereHas('favourites', function ($q) use ($familyIds) {
                            $q->whereIn('family_id', $familyIds);
                        });
                        break;
                    case 'basket_not_purchased':
                        $query->orWhereHas('orders', function ($q) use ($familyIds) {
                            $q->where('state', OrderStateEnum::CREATING->value)
                                ->whereHas('transactions', function ($tq) use ($familyIds) {
                                    $tq->whereIn('family_id', $familyIds);
                                });
                        });
                        break;
                }
            }
        } else {
            // AND conditions - original behavior
            if ($behaviours['purchased'] ?? false) {
                $query->whereHas('orders', function ($q) use ($familyIds) {
                    $q->where('state', '!=', OrderStateEnum::CREATING->value)
                        ->whereHas('transactions', function ($tq) use ($familyIds) {
                            $tq->whereIn('family_id', $familyIds);
                        });
                });
            }

            if ($behaviours['favourited'] ?? false) {
                $query->whereHas('favourites', function ($q) use ($familyIds) {
                    $q->whereIn('family_id', $familyIds);
                });
            }

            if ($behaviours['basket_not_purchased'] ?? false) {
                $query->whereHas('orders', function ($q) use ($familyIds) {
                    $q->where('state', OrderStateEnum::CREATING->value)
                        ->whereHas('transactions', function ($tq) use ($familyIds) {
                            $tq->whereIn('family_id', $familyIds);
                        });
                });
            }
        }

        return $query;
    }
}
