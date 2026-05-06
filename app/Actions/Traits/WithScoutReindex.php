<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 Apr 2026 15:56:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Support\Facades\Artisan;

trait WithScoutReindex
{
    protected function runScoutReindex(string $modelClass, bool $reindex = true, bool $reset = false): void
    {
        if ($reset) {
            Artisan::call('scout:flush', [
                'model' => $modelClass,
            ]);
        }

        if ($reindex) {
            Artisan::call('scout:queue-import', [
                'model' => $modelClass,
                '--chunk' => 1000,
            ]);
        }
    }
}
