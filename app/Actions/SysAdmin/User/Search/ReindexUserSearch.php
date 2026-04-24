<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 21:11:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexUserSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:users';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(User::class, $reindex, $reset);
    }

}
