<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:06:41 Central Indonesia Time, Bali Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\CRM\Prospect;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexProspectSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:prospects';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Prospect::class, $reindex, $reset);
    }
}
