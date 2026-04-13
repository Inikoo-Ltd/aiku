<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 7 Apr 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots\Filters;

use App\Enums\CRM\Prospect\ProspectStateEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;

class FilterProspectsNeverContacted
{
    /**
     * Apply the "Never Contacted" filter to the query.
     *
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $neverContacted = Arr::get($filters, 'never_contacted');
        $isNeverContactedActive = is_array($neverContacted) ? ($neverContacted['value'] ?? false) : $neverContacted;

        if ($isNeverContactedActive) {
            $query->where('prospects.state', ProspectStateEnum::NO_CONTACTED->value);
        }

        return $query;
    }
}
