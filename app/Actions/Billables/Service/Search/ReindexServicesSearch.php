<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Service\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Billables\Service;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexServicesSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:services';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Service::class, $reindex, $reset);
    }


}
