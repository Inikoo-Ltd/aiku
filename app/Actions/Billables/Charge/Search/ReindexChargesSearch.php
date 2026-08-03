<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Charge\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Billables\Charge;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexChargesSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:charges';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Charge::class, $reindex, $reset);
    }


}
