<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Mar 2024 20:30:23 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

trait HasFetchReset
{
    private function setAuroraConnection($databaseName, $dbSuffix): void
    {
        $databaseSettings = data_get(config('database.connections'), 'aurora');
        data_set($databaseSettings, 'database', $databaseName.$dbSuffix);
        config(['database.connections.aurora' => $databaseSettings]);
        DB::connection('aurora');
        DB::purge('aurora');
    }

    public function stepTime(): string
    {
        $rollTime = microtime(true) - $this->timeStart;
        $diff = microtime(true) - $this->timeLastStep;
        $this->timeLastStep = microtime(true);

        return "\t".round($rollTime, 2).'s'."\t\t".round($diff, 2).'s';
    }
}
