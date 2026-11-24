<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UI;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Sorts\Sort;

trait WithWaitingSinceSort
{
    protected function waitingSinceSort(string $waitingSinceSql, string $tieBreakSql): AllowedSort
    {
        return AllowedSort::custom('waiting_since', new class ($waitingSinceSql, $tieBreakSql) implements Sort {
            public function __construct(private readonly string $waitingSinceSql, private readonly string $tieBreakSql)
            {
            }

            public function __invoke(Builder $query, bool $descending, string $property): void
            {
                $direction = $descending ? 'desc' : 'asc';
                $query->orderByRaw($this->waitingSinceSql.' '.$direction.', '.$this->tieBreakSql.' '.$direction);
            }
        });
    }
}
