<?php

/*
 * Author: Louis Perez
 * Created: Thu, 9 July 2026 16:23
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Services\CustomSort;

use Illuminate\Database\Eloquent\Builder;

class RandomSort implements \Spatie\QueryBuilder\Sorts\Sort
{
    public function __invoke(Builder $query, $descending, string $property): Builder
    {
        return $query->inRandomOrder();
    }
}
