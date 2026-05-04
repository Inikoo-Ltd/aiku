<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 21:12:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\SysAdmin\Guest;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexGuestSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:users';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Guest::class, $reindex, $reset);
    }

}
