<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:43:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Inventory\Location;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexLocationsSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:locations';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Location::class, $reindex, $reset);
    }

}
