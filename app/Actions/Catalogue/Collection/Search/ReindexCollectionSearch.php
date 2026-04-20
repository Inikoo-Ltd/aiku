<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 20:45:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Catalogue\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexCollectionSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:collections';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Collection::class, $reindex, $reset);
    }

}
