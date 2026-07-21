<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Feb 2025 14:14:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin;

use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CleanUserCaches
{
    use AsAction;

    public string $commandSignature = 'users:clean_cache {--i|id=} {--u|username=}';


    public function handle(User $user): void
    {
        Cache::tags('auth-user:'.$user->id)->flush();
        Cache::tags('grp-first-load-props:'.$user->id)->flush();
    }

    public function clearPermissionsCache(User $user): void
    {
        $this->handle($user);
    }


    public function asCommand(Command $command): int
    {
        $query = DB::table('users')->select('id')->orderBy('id');

        if ($command->hasOption('username') && $command->option('username')) {
            $query->where('username', $command->option('username'));
        }

        if ($command->hasOption('id') && $command->option('id')) {
            $query->where('id', $command->option('id'));
        }


        $count = $query->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->chunk(
            1000,
            function (Collection $modelsData) use ($bar) {
                foreach ($modelsData as $modelId) {
                    $user = new User();
                    $instance = $user->withTrashed()->find($modelId->id);

                    $this->handle($instance);
                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->info("");

        return 0;
    }


}
