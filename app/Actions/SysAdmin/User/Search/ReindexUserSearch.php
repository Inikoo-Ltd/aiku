<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 21:11:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\Search;

use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Artisan;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexUserSearch
{
    use AsAction;

    public string $commandSignature = 'reindex_search:users';

    public function handle(bool $reset = false): void
    {
        if ($reset) {
            Artisan::call('scout:flush', [
                'model' => User::class
            ]);
        }

        Artisan::call('scout:queue-import', [
            'model'   => User::class,
            '--chunk' => 1000
        ]);
    }

}
