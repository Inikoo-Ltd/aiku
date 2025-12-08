<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 30 Apr 2023 12:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PrepareAuroraInstance
{
    use AsAction;

    public string $commandSignature = 'fetch:prepare_aurora {token} {organisation}';

    public function getCommandDescription(): string
    {
        return 'Save token and aiku url in aurora database.';
    }

    public function asCommand(Command $command): int
    {
        try {
            $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        } catch (Exception) {
            $command->error('Organisation not found');

            return 1;
        }
        $aurora_db = Arr::get($organisation->data, 'source.aurora_db');
        if ($aurora_db) {
            $database_settings = data_get(config('database.connections'), 'aurora');
            data_set($database_settings, 'database', $aurora_db);
            config(['database.connections.aurora' => $database_settings]);
            DB::connection('aurora');
            DB::purge('aurora');

            DB::connection('aurora')->table('Account Data')
                ->update(['aiku_token' => $command->argument('token')]);

            if (app()->environment('local') || app()->environment('staging') || app()->environment('production')) {
                /** @noinspection HttpUrlsUsage */
                DB::connection('aurora')->table('Account Data')
                    ->update(['aiku_url' => (app()->isLocal() ? 'http://' : 'https://').$command->argument('organisation').'.'.config('app.domain')]);
            }
        }

        return 0;
    }
}
