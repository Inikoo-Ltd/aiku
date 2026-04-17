<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 21:12:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest\Search;

use App\Models\SysAdmin\Guest;
use Illuminate\Support\Facades\Artisan;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexGuestSearch
{
    use AsAction;

    public string $commandSignature = 'reindex_search:users';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        if ($reset) {
            Artisan::call('scout:flush', [
                'model' => Guest::class
            ]);
        }
        if ($reindex) {
            Artisan::call('scout:queue-import', [
                'model'   => Guest::class,
                '--chunk' => 1000
            ]);
        }
    }

}
