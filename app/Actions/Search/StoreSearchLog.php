<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 02:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Helpers\SearchLog;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreSearchLog
{
    use AsAction;

    public function handle(array $modelData): SearchLog
    {
        return SearchLog::create($modelData);
    }
}
