<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use Illuminate\Support\Arr;
use Laravel\Scout\Builder;

trait WithRawSearchResults
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rawDocuments(Builder $query): array
    {
        return Arr::pluck(Arr::get($query->raw(), 'hits', []), 'document');
    }
}
